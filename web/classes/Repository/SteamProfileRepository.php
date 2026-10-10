<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    class SteamProfileRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        public function find(string $communityId) : ?array
        {
            $sql = "
                SELECT
                    `status`, `avatar`, `fetched`
                FROM
                    `hlstats_SteamProfiles`
                WHERE
                    `communityId` = :communityId
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['communityId' => $communityId]);

                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (PDOException $e) {
                $this->logger->error("Failed to read Steam profile cache for {$communityId}: " . $e->getMessage());
                return null;
            }
        }

        public function save(string $communityId, string $status, string $avatar, int $fetched) : void
        {
            $sql = "
                INSERT INTO
                    `hlstats_SteamProfiles` (`communityId`, `status`, `avatar`, `fetched`)
                VALUES
                    (:communityId, :status, :avatar, :fetched)
                ON DUPLICATE KEY UPDATE
                    `status` = VALUES(`status`),
                    `avatar` = VALUES(`avatar`),
                    `fetched` = VALUES(`fetched`)
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'communityId' => $communityId,
                    'status' => $status,
                    'avatar' => $avatar,
                    'fetched' => $fetched,
                ]);
            } catch (PDOException $e) {
                $this->logger->error("Failed to write Steam profile cache for {$communityId}: " . $e->getMessage());
            }
        }
    }
