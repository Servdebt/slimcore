<?php

namespace Jupitern\Slim3\ServiceProviders;

use League\Plates\Engine;

class Plates implements ProviderInterface
{
    public static function register($serviceName, array $settings = [])
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

        app()->getContainer()[$serviceName] = $engine;
    }
}
