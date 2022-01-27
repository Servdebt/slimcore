<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Lib\SMS\NosRESTGateway;
use Servdebt\SlimCore\App;

class NosSmsService implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = [])
    {
        $app->registerInContainer($serviceName, new NosRESTGateway($settings));
    }

}
