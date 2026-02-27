<?php

    namespace Config;

    class DatabaseOptions
    {
        private string $host;
        private string $user;
        private string $pass;
        private string $name;
        private bool $pconnect;
        private string $charset;

        public function __construct(array $params)
        {
            $this->host     = $params['host'] ?? 'localhost';
            $this->user     = $params['user'] ?? '';
            $this->pass     = $params['pass'] ?? '';
            $this->name     = $params['name'] ?? '';
            $this->pconnect = $params['pconnect'] ?? false;
            $this->charset  = $params['charset'] ?? 'utf8mb4';
        }

        public function getHost(): string    { return $this->host; }
        public function getUser(): string    { return $this->user; }
        public function getPass(): string    { return $this->pass; }
        public function getName(): string    { return $this->name; }
        public function isPersistent(): bool { return $this->pconnect; }
        public function getCharset(): string { return $this->charset; }
    }