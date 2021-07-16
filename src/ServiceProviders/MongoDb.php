<?php

namespace Servdebt\SlimCore\ServiceProviders;

use \MongoDB\Client;
use SequelMongo\QueryBuilder;
use Servdebt\SlimCore\App;

class MongoDb implements ProviderInterface
{

    public static function register(App $app, $serviceName, array $settings = [])
    {
        $app->registerInContainer($serviceName, function($c) use ($serviceName, $settings) {

            $con = new Client($settings["uri"], $settings["options"]);

            /** @var \MongoDB\Database $con */
            $con = $con->selectDatabase($settings['db']);

            if ((bool)$settings["setGlobal"]) {
                // Set a global connection to be used on all new QueryBuilders
                QueryBuilder::setGlobalConnection($con);
            }

            return $con;
        });
    }

}