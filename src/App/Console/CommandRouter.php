<?php

namespace Servdebt\SlimCore\App\Console;

class CommandRouter
{
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function execute($argv)
    {
        ob_start();
        set_time_limit(0);
        ini_set('memory_limit', '2000M');

        // get params from command line
        $cliCommandParts = (array)$argv;

        // remove the cli.php param
        array_shift($cliCommandParts);

        if (in_array($cliCommandParts[0] ?? '', [\Servdebt\SlimCore\App::DEVELOPMENT, \Servdebt\SlimCore\App::STAGING, \Servdebt\SlimCore\App::PRODUCTION])) {
            array_shift($cliCommandParts);
        }

        // early return: if no params given, return HELP
        if (empty($cliCommandParts)) {
            return $this->app->resolveRoute([HelpCommand::class, "show"], []);
        }
    
        // find where params start being key=val pairs
        for ($paramsStartPos=0; $paramsStartPos<count($cliCommandParts); $paramsStartPos++) {
            if (str_contains($cliCommandParts[$paramsStartPos], '=')) break;
        }
    
        // split command from params
        $commandParts = array_slice($cliCommandParts, 0, $paramsStartPos);
        $paramsParts = array_slice($cliCommandParts, $paramsStartPos, count($cliCommandParts)-1);
    
        // early return: if no command is given
        if (count($cliCommandParts) == 0) {
            app()->notFound();
        }
        // early return: if only command name is given, return HELP for given command
        elseif (count($commandParts) == 1) {
            return $this->app->resolveRoute([HelpCommand::class, 'show'], ['command' => $cliCommandParts[0]]);

        }

        $method = array_pop($commandParts);
        $class = array_pop($commandParts);
        
        $params = [];
        for ($i=0; $i<count($paramsParts); ++$i) {
            $parts = explode("=", $paramsParts[$i], 2);
            if (count($parts) != 2) {
                app()->notFound();
            }
            $params[$parts[0]] = $parts[1];
        }

        $namespace = "\\App\\Console". (count($commandParts) > 0 ? "\\".implode('\\', $commandParts) : "");
        $response = $this->tryResolveRoute($namespace, $class, $method, $params);
        
        if ($response === false) {
            $namespace = "\\Servdebt\\SlimCore\\App\\Console". (count($commandParts) > 0 ? "\\".implode('\\', $commandParts) : "");
            $response = $this->tryResolveRoute($namespace, $class, $method, $params);
        }

        if ($response === false) {
            app()->notFound();
        }

        return $response;

    }

    private function tryResolveRoute($namespace, $class, $method, $params) {
        try {
            return $this->app->resolveRoute([$namespace.'\\'.$class, $method], $params);
        } catch (\Slim\Exception\HttpNotFoundException  $exception) {
            return false;
        } catch (\ReflectionException $exception) {
            app()->notFound();
        }
    }

}