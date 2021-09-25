<?php

namespace Jupitern\Slim3\ServiceProviders;
use \MongoDB\Client;
use SequelMongo\QueryBuilder;

class MongoDb implements ProviderInterface
{

    public static function register($serviceName, array $settings = [])
    {
        app()->getContainer()[$serviceName] = function ($c) use($serviceName, $settings) {

            $con = new Client($settings["uri"], $settings["options"]);

            /** @var \MongoDB\Database $con */
            $con = $con->selectDatabase($settings['db']);

            if ((bool)$settings["setGlobal"]) {
                // Set a global connection to be used on all new QueryBuilders
                QueryBuilder::setGlobalConnection($con);
            }

            return $con;
        };
    }

}