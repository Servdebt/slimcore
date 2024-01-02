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

    protected function ask($question, $color = '92m'): string|false
    {
        echo "\033[{$color}{$question} \033[0m".PHP_EOL;
        return readline();
    }

    protected function output($str, $color = '95m'): void
    {
        echo "\033[{$color}{$str} \033[0m".PHP_EOL;
    }

}