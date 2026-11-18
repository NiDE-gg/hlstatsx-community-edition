<?php

    namespace Database;

    use Config\DatabaseOptions;
    use PDO;
    use PDOException;
    use Utils\Logger;

    class PDODriver
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(DatabaseOptions $options, Logger $logger)
        {
            $this->logger = $logger;

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $options->getHost(),
                $options->getName(),
                $options->getCharset()
            );

            $driverOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            if ($options->isPersistent()) {
                $driverOptions[PDO::ATTR_PERSISTENT] = true;
            }

            try {
                $this->pdo = new PDO($dsn, $options->getUser(), $options->getPass(), $driverOptions);
                // The legacy $db layer runs on this connection too (includes/class_db.php),
                // and always set this collation; the DSN only sets the charset
                if (defined('DB_COLLATE') && preg_match('/^[a-z0-9_]+$/', DB_COLLATE)) {
                    $this->pdo->exec("SET collation_connection = '" . DB_COLLATE . "'");
                }
            } catch (PDOException $e) {
                $this->logger->error('Database connection failed: ' . $e->getMessage());
                // The PDOException message names the DB user and host; it only goes to the log
                die('Database connection failed. The details have been written to the web server error log.');
            }
        }

        public function getPDO(): PDO
        {
            return $this->pdo;
        }
    }