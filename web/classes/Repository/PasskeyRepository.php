<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    /**
     * Passkeys (WebAuthn credentials) of the admin accounts, in hlstats_Passkeys.
     * credential_id and user_handle are raw bytes, public_key a PEM string.
     * Read errors are logged and return nothing, so a missing table (update not
     * run yet) only means "no passkey".
     */
    class PasskeyRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        /** @return array<int, array<string, mixed>> the user's passkeys, oldest first */
        public function forUser(string $username) : array
        {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT `id`, `username`, `credential_id`, `user_handle`, `public_key`, `sign_count`, `label`, `created`, `last_used`
                    FROM `hlstats_Passkeys`
                    WHERE `username` = :username
                    ORDER BY `id`
                ");
                $stmt->execute(['username' => $username]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $this->logger->error("Failed to read passkeys: " . $e->getMessage());
                return [];
            }
        }

        public function findByCredentialId(string $credentialId) : ?array
        {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT `id`, `username`, `credential_id`, `user_handle`, `public_key`, `sign_count`, `label`, `created`, `last_used`
                    FROM `hlstats_Passkeys`
                    WHERE `credential_id` = :credential_id
                    LIMIT 1
                ");
                $stmt->execute(['credential_id' => $credentialId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (PDOException $e) {
                $this->logger->error("Failed to read a passkey: " . $e->getMessage());
                return null;
            }
        }

        /** @return array<string, int> username => number of passkeys */
        public function countsByUser() : array
        {
            try {
                $counts = [];
                foreach ($this->pdo->query("SELECT `username`, COUNT(*) AS `n` FROM `hlstats_Passkeys` GROUP BY `username`") as $row) {
                    $counts[$row['username']] = (int) $row['n'];
                }
                return $counts;
            } catch (PDOException $e) {
                $this->logger->error("Failed to count passkeys: " . $e->getMessage());
                return [];
            }
        }

        public function countForUser(string $username) : int
        {
            return count($this->forUser($username));
        }

        // The same random handle for all passkeys of one account, created with the first
        public function userHandle(string $username) : string
        {
            foreach ($this->forUser($username) as $row) {
                return $row['user_handle'];
            }
            return random_bytes(32);
        }

        public function add(string $username, string $credentialId, string $userHandle, string $publicKey, int $signCount, string $label) : bool
        {
            try {
                return $this->pdo->prepare("
                    INSERT INTO `hlstats_Passkeys`
                        (`username`, `credential_id`, `user_handle`, `public_key`, `sign_count`, `label`, `created`, `last_used`)
                    VALUES
                        (:username, :credential_id, :user_handle, :public_key, :sign_count, :label, :created, 0)
                ")->execute([
                    'username' => $username,
                    'credential_id' => $credentialId,
                    'user_handle' => $userHandle,
                    'public_key' => $publicKey,
                    'sign_count' => $signCount,
                    'label' => $label,
                    'created' => time(),
                ]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to save a passkey: " . $e->getMessage());
                return false;
            }
        }

        // Records a successful sign-in with the passkey and its new signature counter
        public function touch(int $id, int $signCount) : void
        {
            try {
                $this->pdo->prepare("UPDATE `hlstats_Passkeys` SET `sign_count` = :sign_count, `last_used` = :now WHERE `id` = :id")
                    ->execute(['sign_count' => $signCount, 'now' => time(), 'id' => $id]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to update a passkey: " . $e->getMessage());
            }
        }

        public function rename(int $id, string $username, string $label) : bool
        {
            try {
                $stmt = $this->pdo->prepare("UPDATE `hlstats_Passkeys` SET `label` = :label WHERE `id` = :id AND `username` = :username");
                $stmt->execute(['label' => $label, 'id' => $id, 'username' => $username]);
                return true;
            } catch (PDOException $e) {
                $this->logger->error("Failed to rename a passkey: " . $e->getMessage());
                return false;
            }
        }

        public function delete(int $id, string $username) : bool
        {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM `hlstats_Passkeys` WHERE `id` = :id AND `username` = :username");
                $stmt->execute(['id' => $id, 'username' => $username]);
                return $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                $this->logger->error("Failed to delete a passkey: " . $e->getMessage());
                return false;
            }
        }

        public function deleteAllForUser(string $username) : int
        {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM `hlstats_Passkeys` WHERE `username` = :username");
                $stmt->execute(['username' => $username]);
                return $stmt->rowCount();
            } catch (PDOException $e) {
                $this->logger->error("Failed to delete passkeys: " . $e->getMessage());
                return 0;
            }
        }
    }
?>
