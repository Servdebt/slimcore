<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Lib\SMS\NosRESTGateway;

class NosSmsService implements ProviderInterface
{

    public static function register($serviceName, array $settings = [])
    {
        $nosRESTGateway = new NosRESTGateway($settings);
        app()->registerInContainer(serviceName, $nosRESTGateway);
    }

}