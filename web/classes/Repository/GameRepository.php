<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    class GameRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        public function getGameCodes() : ?array
        {
            static $gameCodes = null;
            if ($gameCodes !== null) {
                return $gameCodes;
            }

            $sql = "
                SELECT 
                    code 
                FROM 
                    hlstats_Games 
                WHERE 
                    hidden = '0'
            ";

            try {
                $stmt = $this->pdo->query($sql);
                $gameCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($gameCodes)) {
                    $this->logger->error('No games found in database with hidden=0');
                    return null;
                }
            } catch (PDOException $e) {
                $this->logger->error('Failed to fetch game codes: ' . $e->getMessage());
                return null;
            }

            return $gameCodes;
        }

        public function getGameByCode(string $gameCode, string $field) : ?string
        {
            static $allowedFields = ['name', 'realgame'];

            if (!in_array($field, $allowedFields, true)) {
                $this->logger->error("Invalid field '{$field}' passed to function getGameByCode.");
                return null;
            }

            $sql = "
                SELECT
                    `{$field}`
                FROM
                    hlstats_Games
                WHERE
                    hlstats_Games.code = :code
                LIMIT 1
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':code' => $gameCode]);
                $name = $stmt->fetchColumn();

                if ($name === false) {
                    $this->logger->warning("Game with code '{$gameCode}' not found.");
                    return null;
                }

                return $name;
            } catch (PDOException $e) {
                $this->logger->error("Failed to fetch field '{$field}' for code '{$gameCode}': " . $e->getMessage());
                return null;
            }
        }
    }
