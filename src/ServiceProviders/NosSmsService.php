<?php

namespace Jupitern\Slim3\ServiceProviders;
use Lib\SMS\NosRESTGateway;

class NosSmsService implements ProviderInterface
{

    public static function register($serviceName, array $settings = [])
    {
        $nosRESTGateway = new NosRESTGateway($settings);
        app()->getContainer()[$serviceName] = $nosRESTGateway;
    }

}