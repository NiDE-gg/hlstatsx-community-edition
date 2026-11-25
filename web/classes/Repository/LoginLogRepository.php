<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    /**
     * Admin sign-in history, in hlstats_LoginLog: one row per sign-in attempt or
     * password confirmation (time, account, address, method, result). Errors are
     * logged and ignored, so a missing table never blocks the login.
     */
    class LoginLogRepository
    {
        // Rows older than this are deleted as new ones are written
        const RETENTION = 7776000;

        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        public function add(int $time, string $username, string $address, string $method, string $result) : void
        {
            try {
                $this->pdo->prepare("
                    INSERT INTO `hlstats_LoginLog` (`time`, `username`, `address`, `method`, `result`)
                    VALUES (:time, :username, :address, :method, :result)
                ")->execute([
                    'time' => $time,
                    'username' => mb_substr($username, 0, 64),
                    'address' => substr($address, 0, 45),
                    'method' => $method,
                    'result' => $result,
                ]);

                // A few rows at a time, so no request pays for a long backlog
                $this->pdo->prepare("DELETE FROM `hlstats_LoginLog` WHERE `time` < :cut LIMIT 100")
                    ->execute(['cut' => $time - self::RETENTION]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to record a sign-in: " . $e->getMessage());
            }
        }

        // Whether $address already has a row with $result since $since
        public function hasSince(string $address, string $result, int $since) : bool
        {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM `hlstats_LoginLog`
                    WHERE `address` = :address AND `result` = :result AND `time` >= :since
                    LIMIT 1
                ");
                $stmt->execute(['address' => substr($address, 0, 45), 'result' => $result, 'since' => $since]);
                return (bool) $stmt->fetchColumn();
            } catch (PDOException $e) {
                $this->logger->error("Failed to read the sign-in history: " . $e->getMessage());
                return false;
            }
        }

        /**
         * The account's last successful sign-in before $before and the failed or refused
         * attempts on it since then: ['last' => row|null, 'failures' => int].
         */
        public function summaryBefore(string $username, int $before) : array
        {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT `time`, `address`, `method`
                    FROM `hlstats_LoginLog`
                    WHERE `username` = :username AND `result` = 'success' AND `method` IN ('password', 'passkey', 'code', 'recovery') AND `time` < :before
                    ORDER BY `time` DESC, `id` DESC
                    LIMIT 1
                ");
                $stmt->execute(['username' => $username, 'before' => $before]);
                $last = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*)
                    FROM `hlstats_LoginLog`
                    WHERE `username` = :username AND `result` IN ('failure', 'refused') AND `time` >= :since
                ");
                $stmt->execute(['username' => $username, 'since' => $last ? (int) $last['time'] : 0]);

                return ['last' => $last, 'failures' => (int) $stmt->fetchColumn()];
            } catch (PDOException $e) {
                $this->logger->error("Failed to read the sign-in history: " . $e->getMessage());
                return ['last' => null, 'failures' => 0];
            }
        }

        /**
         * Latest rows, newest first, filtered by account and/or address when given.
         * @return array<int, array<string, mixed>>
         */
        public function latest(string $username, string $address, int $limit) : array
        {
            $where = [];
            $params = [];
            if ($username !== '') {
                $where[] = '`username` = :username';
                $params['username'] = $username;
            }
            if ($address !== '') {
                $where[] = '`address` = :address';
                $params['address'] = $address;
            }

            try {
                $stmt = $this->pdo->prepare("
                    SELECT `id`, `time`, `username`, `address`, `method`, `result`
                    FROM `hlstats_LoginLog`
                    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                    ORDER BY `id` DESC
                    LIMIT " . max(1, min(1000, $limit))
                );
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $this->logger->error("Failed to read the sign-in history: " . $e->getMessage());
                return [];
            }
        }
    }
