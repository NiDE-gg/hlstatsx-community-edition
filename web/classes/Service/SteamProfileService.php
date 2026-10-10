<?php

    namespace Service;

    use Repository\SteamProfileRepository;
    use Utils\Logger;

    /**
     * Steam community avatar / online state, cached in hlstats_SteamProfiles so that
     * player pages do not call steamcommunity.com on every view.
     */
    class SteamProfileService
    {
        // Cached profiles are refreshed after this many seconds.
        private const CACHE_TTL = 86400;
        // After a failed fetch, try again after this many seconds.
        private const RETRY_DELAY = 3600;
        // After Steam answers HTTP 429, stop calling it for everyone for this many seconds.
        private const RATE_LIMIT_BACKOFF = 600;
        // Row of hlstats_SteamProfiles used to remember the last HTTP 429 (no real Steam64 ID is 0).
        private const RATE_LIMIT_ROW = '0';
        private const CONNECT_TIMEOUT = 2;
        private const TIMEOUT = 3;

        private SteamProfileRepository $repository;
        private Logger $logger;

        public function __construct(SteamProfileRepository $repository, Logger $logger)
        {
            $this->repository = $repository;
            $this->logger = $logger;
        }

        /**
         * @return array{status: string, avatar: string} avatar is '' when unknown
         */
        public function getProfile(?string $communityId) : array
        {
            $default = ['status' => 'Unknown', 'avatar' => ''];

            // 76561197960265728 is the Steam64 base, i.e. a player without a usable Steam ID (bots, LAN).
            if ($communityId === null || !preg_match('/^7656\d{13}$/', $communityId) || $communityId === '76561197960265728') {
                return $default;
            }

            $now = time();
            $cached = $this->repository->find($communityId);

            if ($cached !== null && (int)$cached['fetched'] > $now - self::CACHE_TTL) {
                return ['status' => $cached['status'], 'avatar' => $cached['avatar']];
            }

            $fallback = $cached !== null ? ['status' => $cached['status'], 'avatar' => $cached['avatar']] : $default;

            $rateLimit = $this->repository->find(self::RATE_LIMIT_ROW);
            if ($rateLimit !== null && (int)$rateLimit['fetched'] > $now - self::RATE_LIMIT_BACKOFF) {
                return $fallback;
            }

            $profile = $this->fetch($communityId, $httpCode);

            if ($profile !== null) {
                $this->repository->save($communityId, $profile['status'], $profile['avatar'], $now);
                return $profile;
            }

            if ($httpCode === 429) {
                $this->repository->save(self::RATE_LIMIT_ROW, '', '', $now);
                return $fallback;
            }

            // Steam unavailable: keep serving what we have and retry later instead of on every view.
            $this->repository->save($communityId, $fallback['status'], $fallback['avatar'], $now - self::CACHE_TTL + self::RETRY_DELAY);

            return $fallback;
        }

        private function fetch(string $communityId, ?int &$httpCode = null) : ?array
        {
            $curl = curl_init("https://steamcommunity.com/profiles/{$communityId}?xml=1");
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::TIMEOUT,
            ]);

            $body = curl_exec($curl);
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($body === false || $httpCode !== 200) {
                $this->logger->warning("Steam profile fetch failed for {$communityId} (HTTP {$httpCode})");
                return null;
            }

            $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

            if ($xml === false || !isset($xml->steamID64)) {
                $this->logger->warning("Steam profile response for {$communityId} is not a profile");
                return null;
            }

            $avatar = (string)$xml->avatarFull;
            if (!preg_match('#^https://[^\s"<>]+$#', $avatar) || strlen($avatar) > 255) {
                $avatar = '';
            }

            return [
                'status' => substr(ucwords((string)$xml->onlineState), 0, 32) ?: 'Unknown',
                'avatar' => $avatar,
            ];
        }
    }
