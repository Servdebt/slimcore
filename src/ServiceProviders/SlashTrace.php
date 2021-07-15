<?php

namespace Servdebt\SlimCore\ServiceProviders;
use SlashTrace\SlashTrace as ST;
use SlashTrace\EventHandler\DebugHandler;

class SlashTrace implements ProviderInterface
{

	public static function register(string $serviceName, array $settings = [])
	{
	    $st = new ST();
        $st->addHandler(new DebugHandler());

        app()->registerInContainer($serviceName, $st);
	}

}