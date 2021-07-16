<?php

namespace Servdebt\SlimCore\ServiceProviders;
use Servdebt\SlimCore\App;
use SlashTrace\SlashTrace as ST;
use SlashTrace\EventHandler\DebugHandler;

class SlashTrace implements ProviderInterface
{

	public static function register(App $app, string $serviceName, array $settings = [])
	{
	    $st = new ST();
        $st->addHandler(new DebugHandler());

        $app->registerInContainer($serviceName, $st);
	}

}