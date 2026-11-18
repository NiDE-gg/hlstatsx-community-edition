<?php

    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/autoload.php';

    use Config\DatabaseOptions;
    use Database\PDODriver;
    use Utils\Logger;
    use Repository\OptionsRepository;
    use Repository\DiscordCacheRepository;
    use Repository\GameRepository;
    use Repository\LoginFailureRepository;
    use Repository\PlayerRepository;
    use Repository\ServerLoadRepository;
    use Repository\SteamProfileRepository;
    use Repository\TrendRepository;
    use Service\DiscordService;
    use Service\OptionService;
    use Service\SteamProfileService;

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
        // SCRIPT_NAME, not PHP_SELF: PHP_SELF also carries whatever path the visitor
        // adds after the script name, and every link on the page is built from this
        $defaultScriptUrl = (string) ($_SERVER['SCRIPT_NAME'] ?? '') ?: '/hlstats.php';

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

    $container->set(SteamProfileRepository::class, function($c) {
        return new SteamProfileRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(ServerLoadRepository::class, function($c) {
        return new ServerLoadRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(TrendRepository::class, function($c) {
        return new TrendRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(LoginFailureRepository::class, function($c) {
        return new LoginFailureRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(SteamProfileService::class, function($c) {
        return new SteamProfileService($c->get(SteamProfileRepository::class), $c->get('logger'));
    });

    $container->set(DiscordCacheRepository::class, function($c) {
        return new DiscordCacheRepository($c->get('pdo'), $c->get('logger'));
    });

    $container->set(DiscordService::class, function($c) {
        $options = $c->get(OptionService::class)->getAllOptions();
        $userAgent = 'HLstatsX-CE/' . ($options['version'] ?? 'unknown') . ' (+https://github.com/NiDE-gg/hlstatsx-community-edition)';

        return new DiscordService($c->get(DiscordCacheRepository::class), $c->get('logger'), $userAgent);
    });

    return $container;
