<?php

namespace Servdebt\SlimCore\ServiceProviders;
use MongoDB\Client;
use SequelMongo\QueryBuilder;
use Servdebt\SlimCore\App;

class MongoDb implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = []): void
    {
        $conn = new Client($settings["uri"], $settings["options"]);

        /** @var \MongoDB\Database $conn */
        $conn = $conn->selectDatabase($settings['db']);

        if ((bool)$settings["setGlobal"]) {
            // Set a global connection to be used on all new QueryBuilders
            QueryBuilder::setGlobalConnection($conn);
        }

        $app->registerInContainer($serviceName, $conn);
    }

}