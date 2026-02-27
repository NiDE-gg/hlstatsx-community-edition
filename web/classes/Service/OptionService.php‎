<?php

    namespace Service;

    use Repository\OptionsRepository;
    use Utils\Logger;

    class OptionService
    {
        private OptionsRepository $optionsRepo;
        private Logger $logger;

        private string $defaultScriptUrl;

        public function __construct(OptionsRepository $optionsRepo, Logger $logger, string $defaultScriptUrl)
        {
            $this->optionsRepo = $optionsRepo;
            $this->logger = $logger;
            $this->defaultScriptUrl = $defaultScriptUrl;
        }

        public function getAllOptions(): array
        {
            $options = $this->optionsRepo->getAllOptions();
            if (empty($options)) {
                return [];
            }

            return $this->validateOptions($options);
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