<?php

    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/autoload.php';

    use Config\DatabaseOptions;
    use Database\PDODriver;
    use Utils\Logger;
    use Repository\OptionsRepository;
    use Repository\GameRepository;
    use Repository\PlayerRepository;
    use Service\OptionService;
    use Cache\CacheCleaner;

    $container = new class
    {
        private $instances = [];
        private $factories = [];

        public function set(string $id, callable $factory)
        {
            $this->factories[$id] = $factory;
        }

        public function get(string $id)
        {
            if (!isset($this->instances[$id])) {
                if (!isset($this->factories[$id])) {
                    throw new RuntimeException("Service $id not found");
                }

                $this->instances[$id] = ($this->factories[$id])($this);
            }

            return $this->instances[$id];
        }
    };

    $container->set('logger', fn() => new Logger());

    $container->set('db.options', fn() => new DatabaseOptions([
        'host' => DB_ADDR,
        'user' => DB_USER,
        'pass' => DB_PASS,
        'name' => DB_NAME,
        'pconnect' => DB_PCONNECT,
        'charset' => DB_CHARSET,
    ]));

    $container->set('pdo', function ($c) {
        $driver = new PDODriver($c->get('db.options'), $c->get('logger'));
        return $driver->getPdo();
    });

    $container->set(OptionsRepository::class, function($c) {
        return new OptionsRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(OptionService::class, function($c) {
        $defaultScriptUrl = $_SERVER['PHP_SELF'] ?? getenv('PHP_SELF') ?: '/hlstats.php';

        return new OptionService(
            $c->get(OptionsRepository::class),
            $c->get('logger'),
            $defaultScriptUrl
        );
    });

    $container->set(GameRepository::class, function($c) {
        return new GameRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(PlayerRepository::class, function($c) {
        return new PlayerRepository(
            $c->get('pdo'),
            $c->get('logger'),
            $c->get(OptionService::class)
        );
    });

    $container->set(CacheCleaner::class, function($c) {
        return new CacheCleaner(
            $c->get('logger'),
            IMAGE_PATH . '/progress/',
            TREND_CACHE_PROBABILITY,
            true
        );
    });

    return $container;
