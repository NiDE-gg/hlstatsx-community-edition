<?php
	namespace Repository;

	use PDO;
    use PDOException;
	use Utils\Logger;
    use Service\OptionService;

	class PlayerRepository
	{
		private PDO $pdo;
		private Logger $logger;
		private OptionService $optionService;

		public function __construct(PDO $pdo, Logger $logger, OptionService $optionService)
		{
			$this->pdo = $pdo;
			$this->logger = $logger;
            $this->optionService = $optionService;
		}

        public function getPlayerSkillHistory(int $player, int $limit = 30) : ?array
        {
            if ($player < 1) {
                $this->logger->error("Invalid player index {$player} passed to function getPlayerSkillHistory.");
                return null;
            }

            $limit = max(1, $limit);

            $sql = "
                SELECT
                    UNIX_TIMESTAMP(eventTime)
                AS
                    ts, skill, skill_change
                FROM
                    hlstats_Players_History
                WHERE
                    playerId = :player
                ORDER BY eventTime DESC
                LIMIT :limit
            ";

            try {
                $stmt = $this->pdo->prepare($sql);

                $stmt->bindValue(':player', $player, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $this->logger->error('Failed to get player skill history: ' . $e->getMessage());
                return null;
            }
        }

		public function getPlayerSuggestions(string $game, string $search, int $limit = 30) : ?array
		{
			$limit = max(1, $limit);
			$sql = "
				SELECT DISTINCT
					hlstats_PlayerNames.name 
				FROM 
					hlstats_PlayerNames 
				INNER JOIN 
					hlstats_Players 
				ON 
					hlstats_PlayerNames.playerId = hlstats_Players.playerId 
				WHERE 
					game = :game
				AND 
					name LIKE :search
				LIMIT :limit
			";

			try {
				$stmt = $this->pdo->prepare($sql);

                $stmt->bindValue(':game', $game, PDO::PARAM_STR);
                $stmt->bindValue(':search', $search . '%', PDO::PARAM_STR);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                return $stmt->fetchAll(PDO::FETCH_COLUMN);
			} catch (\PDOException $e) {
				$this->logger->error('PDO Exception in getPlayerSuggestions: ' . $e->getMessage());
				return null;
			}
		}

        public function getPlayerRank(string $game, string $rankingType, int $playerPoints, int $playerKills, int $playerDeaths) : ?int
        {
            $allowedRankingType = $this->optionService->getRankingTypeChoices();
            if (empty($allowedRankingType)) {
                $allowedRankingType = ['kills', 'skill']; // default params
            }

            if (!in_array($rankingType, $allowedRankingType, true)) {
                return null;
            }

            $tempDeaths = $playerDeaths;
            if ($tempDeaths == 0) {
                $tempDeaths = 1;
            }

            $kpd = $playerKills / $tempDeaths;

            $sql = "
                SELECT
                    COUNT(*) + 1
                FROM
                    hlstats_Players
                WHERE
                    game = :game
                AND 
                    hideranking = 0
                AND 
                    kills >= 1
                AND (
                    {$rankingType} > :points
                    OR (
                        {$rankingType} = :points
                        AND (kills / IF(deaths = 0, 1, deaths) > :kpd)
                    )
                )
            ";

            try {
                $stmt = $this->pdo->prepare($sql);

                $stmt->execute([
                    'game'  => $game,
                    'points' => $playerPoints,
                    'kpd'   => $kpd,
                ]);

                $count = $stmt->fetchColumn();
                if ($count === false) {
                    return null;
                }

                return (int)$count;
            } catch (PDOException $e) {
                $this->logger->error("Failed to get player rank: " . $e->getMessage());
                return null;
            }
        }
	}
