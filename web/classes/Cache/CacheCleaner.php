<?php

    namespace Cache;

    use Utils\Logger;

    class CacheCleaner
    {
        private Logger $logger;
        private string $cacheDir;
        private int $percent;
        private bool $notice;

        public function __construct(Logger $logger, string $cacheDir, int $percent, bool $notice = true)
        {
            $this->logger = $logger;
            $this->cacheDir = rtrim($cacheDir, '/') . '/';

            if (!is_dir($this->cacheDir)) {
                $this->logger->error("Cache directory does not exist: {$this->cacheDir}. Cache not cleared.");
            }

            if ($percent <= 0 || $percent > 100) {
                $this->logger->error("The probability of triggering is only possible within the range from 1 to 100, correct the argument {$percent}.");
                $percent = 100;
            }

            $this->percent = $percent;
            $this->notice = $notice;
        }

        // 604800 sec - 7 days
        public function cleanOldTrendCache(?int $playerId = null, int $maxAgeSeconds = 604800, int $maxFilesPerPlayer = 5) : int
        {
            if (rand(1, 100) > $this->percent) {
                return 0;
            }

            if (!is_dir($this->cacheDir)) {
                return 0;
            }

            if ($playerId !== null) {
                // If a specific player is specified, we work only with his files
                $deleteFilesCount = $this->cleanPlayerCache($playerId, $maxAgeSeconds, $maxFilesPerPlayer);
            } else {
                // Delete all files older than $maxAgeSeconds seconds in the folder
                $deleteFilesCount = $this->cleanGlobalCache($maxAgeSeconds);
            }

            if ($deleteFilesCount > 0 && $this->notice) {
                if ($playerId === null) {
                    $log = "[CacheCleaner] {$deleteFilesCount} files have been removed from the player image trends cache.";
                } else {
                    $log = "[CacheCleaner] {$deleteFilesCount} files were removed from the player's image trend cache for player {$playerId}.";
                }

                $this->logger->error($log);
            }

            return $deleteFilesCount;
        }

        private function cleanPlayerCache(int $playerId, int $maxAgeSeconds, int $maxFilesPerPlayer) : int
        {
            $pattern = $this->cacheDir . "trend_{$playerId}_*.png";
            $files = glob($pattern);
            if (empty($files)) {
                return 0;
            }

            $deleteFilesCount = 0;
            $now = time();
            $remainingFiles = [];

            // We delete old data if time has expired.
            foreach ($files as $file) {
                if (filemtime($file) < $now - $maxAgeSeconds) {
                    unlink($file);
                    $deleteFilesCount++;
                } else {
                    $remainingFiles[] = $file;
                }
            }

            // Limit on the number of files
            if ($maxFilesPerPlayer > 0 && count($remainingFiles) > $maxFilesPerPlayer) {
                // Sort by modification time (newest first)
                usort($remainingFiles, fn($a, $b) => filemtime($b) - filemtime($a));

                // We only leave the first $maxFilesPerPlayer files
                $filesToDelete = array_slice($remainingFiles, $maxFilesPerPlayer);
                foreach ($filesToDelete as $file) {
                    unlink($file);
                    $deleteFilesCount++;
                }
            }

            return $deleteFilesCount;
        }

        private function cleanGlobalCache(int $maxAgeSeconds) : int
        {
            // We delete old data if time has expired.
            $pattern = $this->cacheDir . "trend_*.png";
            $files = glob($pattern);
            if (empty($files)) {
                return 0;
            }

            $deleteFilesCount = 0;
            $now = time();

            foreach ($files as $file) {
                if (filemtime($file) < $now - $maxAgeSeconds) {
                    unlink($file);
                    $deleteFilesCount++;
                }
            }

            return $deleteFilesCount;
        }

        public function clearDir() : int
        {
            $pattern = $this->cacheDir . "trend_*.png";
            $files = glob($pattern);
            if (empty($files)) {
                return 0;
            }

            $deleteCount = 0;
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deleteCount++;
                }
            }

            return $deleteCount;
        }
    }