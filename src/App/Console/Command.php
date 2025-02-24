<?php

namespace Servdebt\SlimCore\App\Console;
use Psr\Log\LogLevel;

class Command
{

    public float $startTime;
    public ?string $logFilePath = null;


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

    protected function output($question, $color = '95m'): void
    {
        echo "\033[{$color}{$question} \033[0m".PHP_EOL;
    }

    protected function log(string $string, bool $addLog = false, array $context = []): void
    {
        $this->output($string);

        if (!empty($this->logFilePath)) {
            file_put_contents($this->logFilePath, $string.PHP_EOL, FILE_APPEND);
        }

        if ($addLog) {
            addLog(LogLevel::ERROR, $string, $context);
        }
    }

    protected function outputMemoryUsage(): void
    {
        $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->output("Memory usage peak: {$memoryPeak} MB", '93m');
    }

}