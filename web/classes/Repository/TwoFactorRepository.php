<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    /**
     * Two-factor authentication of the admin accounts: the authenticator app secret
     * in hlstats_TwoFactor (one row per account that has it on) and the single-use
     * recovery codes, hashed, in hlstats_RecoveryCodes. Errors are logged; find()
     * tells a database error apart from "not set up", so the login can refuse to
     * go on without the second factor when it can't be read.
     */
    class TwoFactorRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        /**
         * The account's row (secret, last_step, created, last_used), false when it
         * has no two-factor authentication, null on a database error.
         */
        public function find(string $username) : array|false|null
        {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT `username`, `secret`, `last_step`, `created`, `last_used`
                    FROM `hlstats_TwoFactor`
                    WHERE `username` = :username
                    LIMIT 1
                ");
                $stmt->execute(['username' => $username]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: false;
            } catch (PDOException $e) {
                $this->logger->error("Failed to read two-factor settings: " . $e->getMessage());
                return null;
            }
        }

        /**
         * Turns two-factor authentication on for the account with $secret, or replaces
         * its secret, and gives it new recovery codes (hashes). $step is the time step of
         * the code that confirmed the secret, so that code can't be used to sign in.
         */
        public function enable(string $username, string $secret, int $step, array $recoveryHashes) : bool
        {
            try {
                $this->pdo->prepare("
                    REPLACE INTO `hlstats_TwoFactor`
                        (`username`, `secret`, `last_step`, `created`, `last_used`)
                    VALUES
                        (:username, :secret, :step, :created, 0)
                ")->execute(['username' => $username, 'secret' => $secret, 'step' => $step, 'created' => time()]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to save two-factor settings: " . $e->getMessage());
                return false;
            }

            return $this->replaceRecoveryCodes($username, $recoveryHashes);
        }

        /**
         * Records $step as used, unless it is not newer than the last one used: the
         * UPDATE both checks and records, so the same code sent twice at once only
         * signs in once. Returns false when the step was already used.
         */
        public function useStep(string $username, int $step) : bool
        {
            try {
                $stmt = $this->pdo->prepare("
                    UPDATE `hlstats_TwoFactor`
                    SET `last_step` = :step, `last_used` = :now
                    WHERE `username` = :username AND `last_step` < :step2
                ");
                $stmt->execute(['step' => $step, 'step2' => $step, 'now' => time(), 'username' => $username]);
                return $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                $this->logger->error("Failed to record a two-factor code: " . $e->getMessage());
                return false;
            }
        }

        /** Turns two-factor authentication off for the account and drops its recovery codes */
        public function disable(string $username) : bool
        {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM `hlstats_TwoFactor` WHERE `username` = :username");
                $stmt->execute(['username' => $username]);
                $removed = $stmt->rowCount() > 0;
                $this->pdo->prepare("DELETE FROM `hlstats_RecoveryCodes` WHERE `username` = :username")
                    ->execute(['username' => $username]);
                return $removed;
            } catch (PDOException $e) {
                $this->logger->error("Failed to turn two-factor authentication off: " . $e->getMessage());
                return false;
            }
        }

        public function replaceRecoveryCodes(string $username, array $hashes) : bool
        {
            try {
                $this->pdo->prepare("DELETE FROM `hlstats_RecoveryCodes` WHERE `username` = :username")
                    ->execute(['username' => $username]);
                $insert = $this->pdo->prepare("
                    INSERT INTO `hlstats_RecoveryCodes` (`username`, `code_hash`, `used`)
                    VALUES (:username, :hash, 0)
                ");
                foreach ($hashes as $hash) {
                    $insert->execute(['username' => $username, 'hash' => $hash]);
                }
                return true;
            } catch (PDOException $e) {
                $this->logger->error("Failed to save recovery codes: " . $e->getMessage());
                return false;
            }
        }

        /** Marks the recovery code used, once: false when it is unknown or already used */
        public function useRecoveryCode(string $username, string $hash) : bool
        {
            try {
                $stmt = $this->pdo->prepare("
                    UPDATE `hlstats_RecoveryCodes`
                    SET `used` = :now
                    WHERE `username` = :username AND `code_hash` = :hash AND `used` = 0
                ");
                $stmt->execute(['now' => time(), 'username' => $username, 'hash' => $hash]);
                if ($stmt->rowCount() === 0) {
                    return false;
                }
                $this->pdo->prepare("UPDATE `hlstats_TwoFactor` SET `last_used` = :now WHERE `username` = :username")
                    ->execute(['now' => time(), 'username' => $username]);
                return true;
            } catch (PDOException $e) {
                $this->logger->error("Failed to use a recovery code: " . $e->getMessage());
                return false;
            }
        }

        public function unusedRecoveryCodes(string $username) : int
        {
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `hlstats_RecoveryCodes` WHERE `username` = :username AND `used` = 0");
                $stmt->execute(['username' => $username]);
                return (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                $this->logger->error("Failed to count recovery codes: " . $e->getMessage());
                return 0;
            }
        }

        /** @return array<string, array{created: int, last_used: int}> accounts with two-factor authentication */
        public function accounts() : array
        {
            try {
                $accounts = [];
                foreach ($this->pdo->query("SELECT `username`, `created`, `last_used` FROM `hlstats_TwoFactor` ORDER BY `username`") as $row) {
                    $accounts[$row['username']] = ['created' => (int) $row['created'], 'last_used' => (int) $row['last_used']];
                }
                return $accounts;
            } catch (PDOException $e) {
                $this->logger->error("Failed to list two-factor accounts: " . $e->getMessage());
                return [];
            }
        }
    }
