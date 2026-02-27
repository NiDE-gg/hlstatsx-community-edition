<?php

    namespace Service;

    use Repository\OptionsRepository;
    use Utils\Logger;

    class OptionService
    {
        private OptionsRepository $optionsRepo;
        private Logger $logger;

        private string $defaultScriptUrl;

        private ?array $cachedOptions = null;

        public function __construct(OptionsRepository $optionsRepo, Logger $logger, string $defaultScriptUrl)
        {
            $this->optionsRepo = $optionsRepo;
            $this->logger = $logger;
            $this->defaultScriptUrl = $defaultScriptUrl;
        }

        public function getRankingTypeChoices(): ?array
        {
            return $this->optionsRepo->getOptionChoices('rankingtype');
        }

        public function getAllOptions() : array
        {
            if ($this->cachedOptions === null) {
                $options = $this->optionsRepo->getAllOptions();

                if (!empty($options)) {
                    $options = $this->validateOptions($options);
                }

                $this->cachedOptions = $options;
            }

            return $this->cachedOptions;
        }

        private function validateOptions(array $options) : array
        {
            if (isset($options['MinActivity'])) {
                $options['MinActivity'] = $options['MinActivity'] * 86400;
            }

            if (!isset($options['scripturl'])) {
                $options['scripturl'] = $this->defaultScriptUrl;
            }

            return $options;
        }
    }