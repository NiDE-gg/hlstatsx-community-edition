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
                // Fails open on purpose: the password check reads the same database, so
                // with it down nobody can log in anyway, and an error in this table alone
                // (missing after a skipped update, for example) must not lock out every
                // admin. The error is logged, so the limit being off does not go unseen.
                $this->logger->error("Failed to count a login attempt, attempt not limited: " . $e->getMessage());
                return true;
            }
        }

        /**
         * Counts one attempt against $key unless it has to wait: the first $free
         * failures since $since are free, then each one waits 2, 4, 8... seconds
         * after the previous one, at most $maxDelay. The UPDATE both checks and
         * counts, like take(). Returns 0 when the attempt was counted, else the
         * seconds left to wait. Fails open (returns 0) on a database error.
         */
        public function takeDelayed(string $key, int $free, int $maxDelay, int $now, int $since) : int
        {
            // Exponent capped so POW() stays in range however many failures pile up
            $delay = "LEAST(:maxDelay, POW(2, LEAST(20, GREATEST(0, CAST(`failures` AS SIGNED) - :free + 1))))";

            try {
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
                        AND (`failures` < :free2 OR `last_failure` + $delay <= :now2)
                ");
                $stmt->execute(['key' => $key, 'now' => $now, 'now2' => $now, 'free' => $free, 'free2' => $free, 'maxDelay' => $maxDelay]);
                if ($stmt->rowCount() > 0) {
                    return 0;
                }

                $stmt = $this->pdo->prepare("
                    SELECT
                        `last_failure` + $delay - :now
                    FROM
                        `hlstats_LoginFailures`
                    WHERE
                        `key` = :key
                ");
                $stmt->execute(['key' => $key, 'now' => $now, 'free' => $free, 'maxDelay' => $maxDelay]);

                return max(1, (int) ceil((float) $stmt->fetchColumn()));
            } catch (PDOException $e) {
                // Fails open, for the same reasons as take()
                $this->logger->error("Failed to count a login attempt, attempt not limited: " . $e->getMessage());
                return 0;
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
