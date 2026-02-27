<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    class OptionsRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        public function getAllOptions() : array
        {
            $sql = "
                SELECT 
                    `keyname`, `value` 
                FROM 
                    hlstats_Options 
                WHERE 
                    opttype >= 1
            ";

            try {
                $stmt = $this->pdo->query($sql);

                $options = [];
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $options[$row[0]] = $row[1];
                }

                if (empty($options)) {
                    $this->logger->error('No options found in database');
                    return [];
                }

                return $options;
            } catch (PDOException $e) {
                $this->logger->error('Failed to fetch options: ' . $e->getMessage());
                return [];
            }
        }
    }