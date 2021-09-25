<?php

namespace Jupitern\Slim3\ServiceProviders;
use Jupitern\Slim3\Utils\Redis as RedisClient;
use Predis\Client;

class Redis implements ProviderInterface
{

    public static function register($serviceName, array $settings = [])
    {
        app()->getContainer()[$serviceName] = function ($c) use($serviceName, $settings) {

            $con = new RedisClient(new Client($settings));

            return $con;
        };
    }

}