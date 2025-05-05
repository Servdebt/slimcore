<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Servdebt\SlimCore\App;
use Servdebt\SlimCore\Utils\Redis as RedisClient;
use Predis\Client;

class Redis implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = []): void
    {
        $redis = new RedisClient(new Client(
            [
                'scheme' => $settings['scheme'] ?? 'tcp',
                'path' => $settings['path'] ?? '',
                'host' => $settings['host'],
                'port' => $settings['port'] ?? 6379,
            ], [
                'parameters' => [
                    'username' => $settings['username'] ?? 'default',
                    'password' => $settings['password'] ?? '',
                    'database' => $settings['database'],
                ]
            ]
        ));

        $app->registerInContainer($serviceName, $redis);
    }

}