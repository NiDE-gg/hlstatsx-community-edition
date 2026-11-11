<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    /**
     * Failed admin logins, counted per key ("ip:<address>" or "user:<name>").
     * A key's count starts again from 1 once its last failure is older than
     * the window passed in by the caller. Errors are logged and ignored, so a
     * missing table (update not run yet) never blocks the login.
     */
    class LoginFailureRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        // Returns ['failures' => int, 'last_failure' => int], or null when the key has none since $since
        public function find(string $key, int $since) : ?array
        {
            $sql = "
                SELECT
                    `failures`, `last_failure`
                FROM
                    `hlstats_LoginFailures`
                WHERE
                    `key` = :key
                    AND `last_failure` >= :since
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['key' => $key, 'since' => $since]);

                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? ['failures' => (int) $row['failures'], 'last_failure' => (int) $row['last_failure']] : null;
            } catch (PDOException $e) {
                $this->logger->error("Failed to read login failures: " . $e->getMessage());
                return null;
            }
        }

        /**
         * Counts one attempt against $key, unless it already has $limit of them
         * since $since. The UPDATE both checks and counts, so two requests can't
         * take the same last place. Returns false when the key is at its limit.
         */
        public function take(string $key, int $limit, int $now, int $since) : bool
        {
            try {
                // Rows past the window no longer count, so the table stays small
                $this->pdo->prepare("DELETE FROM `hlstats_LoginFailures` WHERE `last_failure` < :since")
                    ->execute(['since' => $since]);

                $this->pdo->prepare("
                    INSERT IGNORE INTO
                        `hlstats_LoginFailures` (`key`, `failures`, `last_failure`)
                    VALUES
                        (:key, 0, :now)
                ")->execute(['key' => $key, 'now' => $now]);

                $stmt = $this->pdo->prepare("
                    UPDATE
                        `hlstats_LoginFailures`
                    SET
                        `failures` = `failures` + 1,
                        `last_failure` = :now
                    WHERE
                        `key` = :key
                        AND `failures` < :limit
                ");
                $stmt->execute(['key' => $key, 'now' => $now, 'limit' => $limit]);

                return $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                $this->logger->error("Failed to count a login attempt: " . $e->getMessage());
                return true;
            }
        }

        // Takes one counted attempt back (a good login, or an attempt refused on another key)
        public function giveBack(string $key) : void
        {
            try {
                $this->pdo->prepare("
                    UPDATE
                        `hlstats_LoginFailures`
                    SET
                        `failures` = `failures` - 1
                    WHERE
                        `key` = :key
                        AND `failures` > 0
                ")->execute(['key' => $key]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to give a login attempt back: " . $e->getMessage());
            }
        }

        public function clear(array $keys) : void
        {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM `hlstats_LoginFailures` WHERE `key` = :key");
                foreach ($keys as $key) {
                    $stmt->execute(['key' => $key]);
                }
            } catch (PDOException $e) {
                $this->logger->error("Failed to clear login failures: " . $e->getMessage());
            }
        }
    }
?>
