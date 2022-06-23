<?php

namespace Servdebt\SlimCore\App\Console;

use ReflectionMethod;
use DirectoryIterator;

class Help extends Command
{

	public function show(string $command = '') :string
    {
        $ret  = PHP_EOL. "usage: php ".ROOT_PATH."cli.php <command-name> <method-name> [parameters...]" . PHP_EOL . PHP_EOL;
        $ret .= "The following ". (empty($command) ? "commands" : "tasks") ." are available:" . PHP_EOL;
      
        if (!empty($command) && is_file(APP_PATH.'Console'.DS.$command.".php")) {
            $fileinfo = new \SplFileInfo(APP_PATH.'Console'.DS.$command.".php");
            $ret .= $this->listClassMethods($fileinfo->getFilename());
        }
        else if (!empty($command) && is_file(__DIR__.DS.$command.".php")) {
            $fileinfo = new \SplFileInfo(__DIR__.DS.$command.".php");
            $ret .= $this->listClassMethods($fileinfo->getFilename(), "\\Servdebt\\SlimCore\\App\Console\\");
        }
        else {
            $iterator = new DirectoryIterator(APP_PATH.'Console');
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $ret .= $this->listClassMethods($fileinfo->getFilename());
                }
            }
            $iterator = new DirectoryIterator(__DIR__);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile() && !in_array($fileinfo->getFilename(), ["Command.php", "CommandRouter.php", "Help.php"])) {
                    $ret .= $this->listClassMethods($fileinfo->getFilename(), "\\Servdebt\\SlimCore\\App\Console\\");
                }
            }
        }

        return $ret;
    }


    /**
     * @param string $filename
     * @param string $namespace
     * @return string
     * @throws \ReflectionException
     */
    private function listClassMethods(string $filename, string $namespace = "\\App\\Console\\") :string
    {
        $ret = "";
        $className = str_replace(".php", "", $filename);
        $class = new \ReflectionClass($namespace.$className);

        if (!$class->isAbstract()) {
            $ret .= "- " . $className . PHP_EOL;

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (str_starts_with($method->getName(), '__')) {
                    continue;
                }
                $ret .= "       ".$method->getName()." ";
                foreach ($method->getParameters() as $parameter) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $ret .= "[".$parameter->getName()."=value] ";
                    }
                    else {
                        $ret .= $parameter->getName()."=value ";
                    }
                }
                $ret .= PHP_EOL;
            }

            $ret .= PHP_EOL;
        }

        return $ret;
    }

}