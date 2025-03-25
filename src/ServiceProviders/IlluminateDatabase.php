<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Illuminate\Database\Capsule\Manager as Capsule;
use Servdebt\SlimCore\App;

class IlluminateDatabase implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = []): void
    {
        if ($app->has('capsule')) {
            $capsule = $app->capsule;
        } else {
            $capsule = new Capsule();
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            $app->registerInContainer('capsule', $capsule);
        }

        $capsule->addConnection($settings, $serviceName);
        $db = $capsule->getConnection($serviceName);

        if ($settings['profiling'] ?? false) {
            $db->enableQueryLog();
        }

        foreach ($settings['inisql'] ?? [] as $sql) {
            $db->statement($sql);
        }

        $app->registerInContainer($serviceName, $db);
    }

}