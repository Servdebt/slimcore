<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Illuminate\Database\Capsule\Manager as Capsule;

class IlluminateDatabase implements ProviderInterface
{

    public static function register($serviceName, array $settings = [])
    {
        $capsule = null;
        if (app()->has('capsule')) {
            $capsule = app()->resolve('capsule');
        } else {
            $capsule = new Capsule();
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            app()->getContainer()['capsule'] = $capsule;
        }

        $capsule->addConnection($settings, $serviceName);

        $db = $capsule->getConnection($serviceName);
        if ((bool)$settings['profiling']) {
            $db->enableQueryLog();
        }

        app()->registerInContainer($serviceName, $db);
    }

}