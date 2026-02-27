<?php

    namespace Database;

    use Config\DatabaseOptions;
    use PDO;
    use PDOException;

    class PDODriver
    {
        private PDO $pdo;

        public function __construct(DatabaseOptions $options)
        {
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
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        public function getPDO(): PDO
        {
            return $this->pdo;
        }
    }