<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Servdebt\SlimCore\Utils\Redis as RedisClient;
use Predis\Client;

class Redis implements ProviderInterface
{

    public static function register($serviceName, array $settings = [])
    {
        app()->registerInContainer($serviceName, function ($c) use($serviceName, $settings) {

            $con = new RedisClient(new Client($settings));

            return $con;
        });
    }

}