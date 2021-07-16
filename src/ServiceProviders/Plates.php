<?php

namespace Servdebt\SlimCore\ServiceProviders;

use League\Plates\Engine;
use Servdebt\SlimCore\App;

class Plates implements ProviderInterface
{
    public static function register(App $app, $serviceName, array $settings = [])
    {
        $engine = new Engine();
        foreach ($settings['templates'] as $name => $path) {
            $engine->addFolder($name, $path, true);
        }

        if (array_key_exists('extensions', $settings)) {
            foreach ($settings['extensions'] as $extension) {
                $engine->loadExtension(new $extension);
            }
        }

        $app->registerInContainer($serviceName, $engine);
    }
}
