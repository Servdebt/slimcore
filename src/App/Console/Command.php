<?php

namespace Servdebt\SlimCore\App\Console;

class Command
{

    public float $startTime;

    public function __construct()
    {
		$this->startTime = microtime(true);
		
        if (app()->getConfig('consoleOutput')) {
            ob_implicit_flush();
            ob_end_flush();
        }
    }

    public function ask($question, $color = '92m'): string|false
    {
        echo "\033[{$color}{$question} \033[0m\n".PHP_EOL;
        return readline();
    }

    public function output($question, $color = '95m'): void
    {
        echo "\033[{$color}{$question} \033[0m\n".PHP_EOL;
    }

}