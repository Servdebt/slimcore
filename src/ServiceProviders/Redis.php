<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Servdebt\SlimCore\App;
use Servdebt\SlimCore\Utils\Redis as RedisClient;
use Predis\Client;

class Redis implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = [])
    {
        $app->registerInContainer($serviceName, function () use($serviceName, $settings) {
            $con = new RedisClient(new Client($settings));

            return $con;
        });
    }

}