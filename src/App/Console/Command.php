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

}