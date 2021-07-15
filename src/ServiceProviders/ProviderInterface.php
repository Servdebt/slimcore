<?php

namespace Servdebt\SlimCore\ServiceProviders;

interface ProviderInterface
{
	public static function register(string $serviceName, array $settings = []);
}