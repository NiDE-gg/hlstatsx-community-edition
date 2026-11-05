<?php

    namespace Service;

    use Repository\DiscordCacheRepository;
    use Utils\Logger;

    /**
     * Discord servers of the voice server list (hlstats_Servers_VoiceComm.serverType = 2).
     *
     * The admin enters an invite (https://discord.gg/abc, discord.com/invite/abc, abc) or
     * a server ID. Two public endpoints are used, no bot or token needed:
     *   - GET /invites/<code>?with_counts=true: name, icon, member and online counts;
     *   - GET /guilds/<id>/widget.json, when "Enable Server Widget" is on in the Discord
     *     server settings: voice channels and online members (at most 100).
     * With an invite, the server ID comes from the invite, so the widget is read too.
     *
     * Results are cached in hlstats_DiscordCache so pages do not call discord.com on every
     * view: CACHE_TTL after an answer, RETRY_DELAY after a failure, and nobody calls Discord
     * until Retry-After has passed after an HTTP 429.
     */
    class DiscordService
    {
        public const ERROR_INVALID_ADDRESS = 'invalid_address';
        public const ERROR_UNKNOWN_INVITE = 'unknown_invite';
        public const ERROR_UNKNOWN_GUILD = 'unknown_guild';
        public const ERROR_WIDGET_DISABLED = 'widget_disabled';
        public const ERROR_RATE_LIMITED = 'rate_limited';
        public const ERROR_UNAVAILABLE = 'unavailable';

        private const API = 'https://discord.com/api/v10';
        private const CDN = 'https://cdn.discordapp.com/';
        private const CACHE_TTL = 300;
        private const RETRY_DELAY = 600;
        // A refresh in progress keeps other requests from starting one for this long.
        private const CLAIM_DELAY = 30;
        private const RATE_LIMIT_ROW = 'ratelimit';
        private const DEFAULT_RATE_LIMIT_BACKOFF = 60;
        private const MAX_RATE_LIMIT_BACKOFF = 3600;
        private const CONNECT_TIMEOUT = 2;
        private const TIMEOUT = 3;
        private const MAX_USERS = 100;
        private const MAX_TEXT = 100;

        private DiscordCacheRepository $repository;
        private Logger $logger;
        private string $userAgent;

        public function __construct(DiscordCacheRepository $repository, Logger $logger, string $userAgent)
        {
            $this->repository = $repository;
            $this->logger = $logger;
            $this->userAgent = $userAgent;
        }

        /**
         * Short text for an error code, for the voice server list.
         */
        public static function errorText(string $error) : string
        {
            switch ($error) {
                case self::ERROR_INVALID_ADDRESS:
                    return 'bad address';
                case self::ERROR_UNKNOWN_INVITE:
                    return 'invalid invite';
                case self::ERROR_UNKNOWN_GUILD:
                    return 'unknown server';
                case self::ERROR_WIDGET_DISABLED:
                    return 'widget off';
                case self::ERROR_RATE_LIMITED:
                case self::ERROR_UNAVAILABLE:
                    return 'unavailable';
                default:
                    // First view: another request is fetching the data
                    return '...';
            }
        }

        /**
         * @return array{type: string, id: string}|null type is "invite" or "guild"
         */
        public static function parseAddress(string $address) : ?array
        {
            $address = trim($address);

            if (preg_match('/^\d{17,20}$/', $address)) {
                return ['type' => 'guild', 'id' => $address];
            }

            if (preg_match('#^(?:https?://)?(?:www\.)?(?:discord\.gg|discord(?:app)?\.com/invite)/([A-Za-z0-9-]{2,32})/?(?:[?\#].*)?$#i', $address, $m)
                || preg_match('/^([A-Za-z0-9-]{2,32})$/', $address, $m))
            {
                return ['type' => 'invite', 'id' => $m[1]];
            }

            return null;
        }

        /**
         * @return array{data: ?array, error: string}
         *   data: null until something could be read, otherwise
         *     guildId, name, icon (?url), invite (?url), online (?int), members (?int),
         *     widget (bool), channels (list of id/name/position), users (list of
         *     name/status/avatar/channel), time (when Discord answered);
         *   error: '' or one of the ERROR_* constants. When a refresh fails, data from
         *     an earlier answer is still returned with the error.
         */
        public function getServer(string $address) : array
        {
            $target = self::parseAddress($address);
            if ($target === null) {
                return ['data' => null, 'error' => self::ERROR_INVALID_ADDRESS];
            }

            $source = $target['type'] . ':' . $target['id'];
            $now = time();
            $cached = $this->repository->find($source);
            $result = $this->fromCache($cached);

            if ($cached !== null && $cached['fetched'] > $now - self::CACHE_TTL) {
                return $result;
            }

            $rateLimit = $this->repository->find(self::RATE_LIMIT_ROW);
            if ($rateLimit !== null && $rateLimit['fetched'] > $now) {
                return $result['data'] !== null ? $result : ['data' => null, 'error' => self::ERROR_RATE_LIMITED];
            }

            // One request refreshes; the others serve the cached row meanwhile
            if (!$this->repository->claim($source, $cached['fetched'] ?? null, $now - self::CACHE_TTL + self::CLAIM_DELAY)) {
                return $result;
            }

            $retryAfter = null;
            $answer = $this->fetch($target, $retryAfter);

            if ($answer['error'] === self::ERROR_RATE_LIMITED) {
                $wait = (int)max(1, min(self::MAX_RATE_LIMIT_BACKOFF, ceil($retryAfter ?? self::DEFAULT_RATE_LIMIT_BACKOFF)));
                $this->repository->save(self::RATE_LIMIT_ROW, null, self::ERROR_RATE_LIMITED, $now + $wait);
                $this->repository->save($source, $cached['data'] ?? null, $result['error'] ?: self::ERROR_RATE_LIMITED, $now - self::CACHE_TTL + $wait);
                return $result['data'] !== null ? $result : ['data' => null, 'error' => self::ERROR_RATE_LIMITED];
            }

            if ($answer['error'] === self::ERROR_UNAVAILABLE) {
                // Discord down or slow: keep the last answer and retry later, not on every view
                $this->repository->save($source, $cached['data'] ?? null, self::ERROR_UNAVAILABLE, $now - self::CACHE_TTL + self::RETRY_DELAY);
                return ['data' => $result['data'], 'error' => self::ERROR_UNAVAILABLE];
            }

            // An answer, or a definite "no" (unknown invite or server, widget disabled)
            if ($answer['data'] !== null) {
                $answer['data']['time'] = $now;
            }
            $this->repository->save($source, $answer['data'] !== null ? json_encode($answer['data']) : null, $answer['error'], $now);

            return $answer;
        }

        private function fromCache(?array $cached) : array
        {
            if ($cached === null) {
                return ['data' => null, 'error' => ''];
            }

            $data = $cached['data'] !== null ? json_decode($cached['data'], true) : null;

            return ['data' => is_array($data) ? $data : null, 'error' => $cached['error']];
        }

        /**
         * @return array{data: ?array, error: string}
         */
        private function fetch(array $target, ?float &$retryAfter) : array
        {
            if ($target['type'] === 'invite') {
                $response = $this->request(self::API . '/invites/' . rawurlencode($target['id']) . '?with_counts=true', $retryAfter);

                if ($response['status'] === 429) {
                    return ['data' => null, 'error' => self::ERROR_RATE_LIMITED];
                }
                // 404 "Unknown Invite"; an invite to a group DM has no server
                if ($response['status'] === 404 || ($response['status'] === 200 && !isset($response['json']['guild']['id']))) {
                    return ['data' => null, 'error' => self::ERROR_UNKNOWN_INVITE];
                }
                if ($response['status'] !== 200 || !is_array($response['json'])) {
                    return ['data' => null, 'error' => self::ERROR_UNAVAILABLE];
                }

                $invite = $response['json'];
                $guildId = (string)$invite['guild']['id'];
                $code = preg_match('/^[A-Za-z0-9-]{2,32}$/', (string)($invite['code'] ?? '')) ? $invite['code'] : $target['id'];

                $data = $this->emptyData($guildId);
                $data['name'] = $this->text($invite['guild']['name'] ?? '');
                $data['icon'] = $this->iconUrl($guildId, $invite['guild']['icon'] ?? null);
                $data['invite'] = 'https://discord.gg/' . $code;
                $data['online'] = isset($invite['approximate_presence_count']) ? (int)$invite['approximate_presence_count'] : null;
                $data['members'] = isset($invite['approximate_member_count']) ? (int)$invite['approximate_member_count'] : null;

                // Voice channels and online members, when the server has its widget on
                if (preg_match('/^\d{17,20}$/', $guildId)) {
                    $widgetRetry = null;
                    $widget = $this->request(self::API . '/guilds/' . $guildId . '/widget.json', $widgetRetry);
                    if ($widget['status'] === 200 && is_array($widget['json'])) {
                        $this->addWidget($data, $widget['json']);
                    }
                }

                return ['data' => $data, 'error' => ''];
            }

            $response = $this->request(self::API . '/guilds/' . $target['id'] . '/widget.json', $retryAfter);

            switch ($response['status']) {
                case 200:
                    if (!is_array($response['json'])) {
                        return ['data' => null, 'error' => self::ERROR_UNAVAILABLE];
                    }
                    $data = $this->emptyData($target['id']);
                    $this->addWidget($data, $response['json']);

                    // The widget's invite channel gives the member count and the icon
                    if ($data['invite'] !== null && preg_match('#/([A-Za-z0-9-]{2,32})$#', $data['invite'], $m)) {
                        $inviteRetry = null;
                        $invite = $this->request(self::API . '/invites/' . $m[1] . '?with_counts=true', $inviteRetry);
                        if ($invite['status'] === 200 && (string)($invite['json']['guild']['id'] ?? '') === $target['id']) {
                            $data['members'] = isset($invite['json']['approximate_member_count']) ? (int)$invite['json']['approximate_member_count'] : null;
                            $data['icon'] = $this->iconUrl($target['id'], $invite['json']['guild']['icon'] ?? null);
                        }
                    }

                    return ['data' => $data, 'error' => ''];
                case 403:
                    return ['data' => null, 'error' => self::ERROR_WIDGET_DISABLED];
                case 404:
                    return ['data' => null, 'error' => self::ERROR_UNKNOWN_GUILD];
                case 429:
                    return ['data' => null, 'error' => self::ERROR_RATE_LIMITED];
                default:
                    return ['data' => null, 'error' => self::ERROR_UNAVAILABLE];
            }
        }

        private function emptyData(string $guildId) : array
        {
            return [
                'guildId' => $guildId,
                'name' => '',
                'icon' => null,
                'invite' => null,
                'online' => null,
                'members' => null,
                'widget' => false,
                'channels' => [],
                'users' => [],
            ];
        }

        private function addWidget(array &$data, array $widget) : void
        {
            $data['widget'] = true;

            if ($data['name'] === '') {
                $data['name'] = $this->text($widget['name'] ?? '');
            }
            if ($data['online'] === null && isset($widget['presence_count'])) {
                $data['online'] = (int)$widget['presence_count'];
            }
            // Set when the widget has an invite channel
            $invite = (string)($widget['instant_invite'] ?? '');
            if ($data['invite'] === null && preg_match('#^https://(?:discord\.gg|discord(?:app)?\.com/invite)/[A-Za-z0-9-]{2,32}$#', $invite)) {
                $data['invite'] = $invite;
            }

            $channels = [];
            foreach (($widget['channels'] ?? []) as $channel) {
                if (is_array($channel) && isset($channel['id'])) {
                    $channels[] = [
                        'id' => (string)$channel['id'],
                        'name' => $this->text($channel['name'] ?? ''),
                        'position' => (int)($channel['position'] ?? 0),
                    ];
                }
            }
            usort($channels, fn($a, $b) => $a['position'] <=> $b['position']);
            $data['channels'] = $channels;

            $users = [];
            foreach (array_slice(is_array($widget['members'] ?? null) ? $widget['members'] : [], 0, self::MAX_USERS) as $member) {
                if (!is_array($member)) {
                    continue;
                }
                $avatar = (string)($member['avatar_url'] ?? '');
                $status = (string)($member['status'] ?? '');
                $users[] = [
                    'name' => $this->text($member['username'] ?? ''),
                    'status' => in_array($status, ['online', 'idle', 'dnd'], true) ? $status : 'online',
                    'avatar' => (strpos($avatar, self::CDN) === 0 && strlen($avatar) <= 512 && !preg_match('/[\s"\'<>]/', $avatar)) ? $avatar : null,
                    'channel' => isset($member['channel_id']) ? (string)$member['channel_id'] : null,
                ];
            }
            $data['users'] = $users;
        }

        private function iconUrl(string $guildId, $hash) : ?string
        {
            if (!is_string($hash) || !preg_match('/^(?:a_)?[0-9a-f]{32}$/', $hash) || !preg_match('/^\d{17,20}$/', $guildId)) {
                return null;
            }

            return self::CDN . "icons/{$guildId}/{$hash}.png?size=64";
        }

        private function text($value) : string
        {
            $value = mb_scrub(trim((string)$value), 'UTF-8');

            return mb_substr($value, 0, self::MAX_TEXT, 'UTF-8');
        }

        /**
         * @return array{status: int, json: mixed} status 0 when Discord could not be reached
         */
        protected function request(string $url, ?float &$retryAfter) : array
        {
            $retryAfter = null;
            $headerRetry = null;

            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$headerRetry) {
                    if (preg_match('/^Retry-After:\s*([0-9.]+)/i', $header, $m)) {
                        $headerRetry = (float)$m[1];
                    }
                    return strlen($header);
                },
            ]);

            // (no curl_close(): it does nothing since PHP 8.0 and is deprecated in 8.5)
            $body = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($body === false || $status === 0) {
                $this->logger->warning("Discord request failed: {$url}");
                return ['status' => 0, 'json' => null];
            }

            $json = json_decode($body, true);

            if ($status === 429) {
                $retryAfter = $headerRetry ?? (isset($json['retry_after']) ? (float)$json['retry_after'] : null);
                $this->logger->warning("Discord rate limit on {$url}, retry after " . ($retryAfter ?? '?') . 's');
            } elseif ($status !== 200 && $status !== 403 && $status !== 404) {
                $this->logger->warning("Discord request {$url} answered HTTP {$status}");
            }

            return ['status' => $status, 'json' => $json];
        }
    }
