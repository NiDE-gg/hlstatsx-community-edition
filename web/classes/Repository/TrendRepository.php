<?php

    namespace Repository;

    use PDO;
    use PDOException;
    use Utils\Logger;

    class TrendRepository
    {
        private PDO $pdo;
        private Logger $logger;

        public function __construct(PDO $pdo, Logger $logger)
        {
            $this->pdo = $pdo;
            $this->logger = $logger;
        }

        /**
         * Per-game trend samples, newest first. Mirrors the query used by
         * show_graph.php's type=1 (per-game kills/headshots/players/slots) PNG renderer.
         *
         * @return array<int, array{timestamp:string, players:string, kills:string, headshots:string, act_slots:string, max_slots:string}>|null
         */
        public function getTrendSamples(string $game, int $limit = 350) : ?array
        {
            $limit = max(1, $limit);

            $sql = "
                SELECT
                    timestamp, players, kills, headshots, act_slots, max_slots
                FROM
                    hlstats_Trend
                WHERE
                    game = :game
                ORDER BY
                    timestamp DESC
                LIMIT :limit
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':game', $game, PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $this->logger->error('Failed to get trend samples: ' . $e->getMessage());
                return null;
            }
        }

        /**
         * Most recent "players" value at or before the given timestamp, used to
         * derive "new players since X" the same way show_graph.php's type=1
         * renderer does (total player count minus this value).
         */
        public function getPlayersAtOrBefore(string $game, int $timestamp) : ?int
        {
            $sql = "
                SELECT
                    players
                FROM
                    hlstats_Trend
                WHERE
                    game = :game
                AND
                    timestamp <= :timestamp
                ORDER BY
                    timestamp DESC
                LIMIT 1
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':game', $game, PDO::PARAM_STR);
                $stmt->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
                $stmt->execute();

                $value = $stmt->fetchColumn();
                return ($value === false) ? null : (int)$value;
            } catch (PDOException $e) {
                $this->logger->error('Failed to get players at/before timestamp: ' . $e->getMessage());
                return null;
            }
        }
    }
