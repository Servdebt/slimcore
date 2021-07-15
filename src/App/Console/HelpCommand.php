<?php

namespace Servdebt\SlimCore\App\Console;
use ReflectionMethod;
use DirectoryIterator;


class HelpCommand extends Command
{

	public function show($command = '')
    {
        $ret  = PHP_EOL. "usage: php ".ROOT_PATH."cli.php <command-name> <method-name> [parameters...]" . PHP_EOL . PHP_EOL;
        $ret .= "The following ". (empty($command) ? "commands" : "tasks") ." are available:" . PHP_EOL;

        $iterator = new DirectoryIterator(APP_PATH.'Console');

        if (!empty($command) && is_file(APP_PATH.'Console'.DS.$command.".php")) {
            $fileinfo = new \SplFileInfo(APP_PATH.'Console'.DS.$command.".php");
            $ret .= $this->listClassMethods($fileinfo->getFilename());
        }
        else {
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile() && $fileinfo->getFilename() != "Command.php") {
                    $ret .= $this->listClassMethods($fileinfo->getFilename());
                }
            }
        }

        return $ret;
    }


    /**
     * @param string $filename
     * @return string
     * @throws \ReflectionException
     */
    private function listClassMethods(string $filename)
    {
        $ret = "";
        $className = str_replace(".php", "", $filename);
        $class = new \ReflectionClass("\\App\\Console\\$className");

        if (!$class->isAbstract()) {
            $ret .= "- " . $className . PHP_EOL;

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (strpos($method->getName(), '__') === 0) {
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