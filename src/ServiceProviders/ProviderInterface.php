<?php

namespace Servdebt\SlimCore\ServiceProviders;

use Servdebt\SlimCore\App;

interface ProviderInterface
{
    public static function register(App $app, string $serviceName, array $settings = []): void;
}