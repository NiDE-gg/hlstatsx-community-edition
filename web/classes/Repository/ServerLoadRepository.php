<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    class ServerLoadRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        /**
         * Raw load samples for a server, newest first, handed to $onSample one row
         * at a time. Mirrors the query used by show_graph.php's type=0 (per-server
         * load/uptime/fps) PNG renderer.
         *
         * The long ranges read the server's whole history (a year of 5-minute
         * samples is about 105,000 rows): the rows are streamed instead of being
         * loaded into one array, which took tens of megabytes per request.
         *
         * @param callable(array{timestamp:string, act_players:string, min_players:string, max_players:string, map:?string, uptime:string, fps:string}):void $onSample
         * @return bool false when the query failed
         */
        public function eachServerLoadSample(int $serverId, bool $limitToRecent, callable $onSample) : bool
        {
            $sql = "
                SELECT
                    timestamp, act_players, min_players, max_players, map, uptime, fps
                FROM
                    hlstats_server_load
                WHERE
                    server_id = :server_id
                ORDER BY
                    timestamp DESC
            " . ($limitToRecent ? ' LIMIT 0, 2500' : '');

            $buffered = $this->pdo->getAttribute(\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY);
            $stmt = null;

            try {
                $this->pdo->setAttribute(\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY, false);

                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':server_id', $serverId, PDO::PARAM_INT);
                $stmt->execute();

                while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $onSample($row);
                }

                return true;
            } catch (PDOException $e) {
                $this->logger->error('Failed to get server load samples: ' . $e->getMessage());
                return false;
            } finally {
                // The connection can't run another query until this result is closed
                if ($stmt !== null) {
                    $stmt->closeCursor();
                }
                $this->pdo->setAttribute(\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY, $buffered);
            }
        }

        /**
         * Live act_players/max_players straight from hlstats_Servers, used to
         * prepend a "right now" point to the trend, same as show_graph.php does
         * when avg_step == 1.
         *
         * @return array{act_players:string, max_players:string}|null
         */
        public function getCurrentServerPlayers(int $serverId) : ?array
        {
            $sql = "
                SELECT
                    act_players, max_players
                FROM
                    hlstats_Servers
                WHERE
                    serverId = :server_id
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':server_id', $serverId, PDO::PARAM_INT);
                $stmt->execute();

                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return ($row === false) ? null : $row;
            } catch (PDOException $e) {
                $this->logger->error('Failed to get current server player counts: ' . $e->getMessage());
                return null;
            }
        }

        public function getAveragePlayersSince(int $serverId, int $sinceTimestamp) : ?float
        {
            $sql = "
                SELECT
                    AVG(act_players) AS players
                FROM
                    hlstats_server_load
                WHERE
                    server_id = :server_id
                AND
                    timestamp >= :since
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':server_id', $serverId, PDO::PARAM_INT);
                $stmt->bindValue(':since', $sinceTimestamp, PDO::PARAM_INT);
                $stmt->execute();

                $value = $stmt->fetchColumn();
                return ($value === false || $value === null) ? null : (float)$value;
            } catch (PDOException $e) {
                $this->logger->error('Failed to get average players since timestamp: ' . $e->getMessage());
                return null;
            }
        }
    }
