<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Servdebt\SlimCore\App;
use Servdebt\SlimCore\Utils\Logger;

class Trace implements ProviderInterface
{

    public static function register(App $app, string $serviceName, array $settings = []): void
    {
        $st = new Logger();

        $app->registerInContainer($serviceName, $st);
    }

}