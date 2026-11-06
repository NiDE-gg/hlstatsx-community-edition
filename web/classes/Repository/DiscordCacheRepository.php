<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    /**
     * hlstats_DiscordCache: what Discord returned for an invite or a server widget.
     * Rows are keyed by "invite:<code>", "guild:<id>", or "ratelimit".
     */
    class DiscordCacheRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        /**
         * @return array{data: ?string, error: string, fetched: int}|null
         */
        public function find(string $source) : ?array
        {
            $sql = "
                SELECT
                    `data`, `error`, `fetched`
                FROM
                    `hlstats_DiscordCache`
                WHERE
                    `source` = :source
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['source' => $source]);

                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return null;
                }

                return ['data' => $row['data'], 'error' => (string)$row['error'], 'fetched' => (int)$row['fetched']];
            } catch (PDOException $e) {
                $this->logger->error("Failed to read Discord cache for {$source}: " . $e->getMessage());
                return null;
            }
        }

        public function save(string $source, ?string $data, string $error, int $fetched) : void
        {
            $sql = "
                INSERT INTO
                    `hlstats_DiscordCache` (`source`, `data`, `error`, `fetched`)
                VALUES
                    (:source, :data, :error, :fetched)
                ON DUPLICATE KEY UPDATE
                    `data` = VALUES(`data`),
                    `error` = VALUES(`error`),
                    `fetched` = VALUES(`fetched`)
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'source' => $source,
                    'data' => $data,
                    'error' => $error,
                    'fetched' => $fetched,
                ]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to write Discord cache for {$source}: " . $e->getMessage());
            }
        }

        /**
         * Lets one request refresh an expired row: moves `fetched` from the value this
         * request read to $claimed. Concurrent requests that read the same row then
         * see a fresh row and serve it instead of all calling Discord at once.
         *
         * @param int|null $previous `fetched` as read by find(), null if there was no row
         * @return bool true if this request won the refresh
         */
        public function claim(string $source, ?int $previous, int $claimed) : bool
        {
            try {
                if ($previous === null) {
                    $stmt = $this->pdo->prepare("
                        INSERT IGNORE INTO
                            `hlstats_DiscordCache` (`source`, `data`, `error`, `fetched`)
                        VALUES
                            (:source, NULL, '', :claimed)
                    ");
                    $stmt->execute(['source' => $source, 'claimed' => $claimed]);
                } else {
                    $stmt = $this->pdo->prepare("
                        UPDATE
                            `hlstats_DiscordCache`
                        SET
                            `fetched` = :claimed
                        WHERE
                            `source` = :source
                            AND `fetched` = :previous
                    ");
                    $stmt->execute(['source' => $source, 'claimed' => $claimed, 'previous' => $previous]);
                }

                return $stmt->rowCount() === 1;
            } catch (PDOException $e) {
                $this->logger->error("Failed to claim Discord cache refresh for {$source}: " . $e->getMessage());
                // Never fall back to calling Discord on every page view
                return false;
            }
        }
    }
