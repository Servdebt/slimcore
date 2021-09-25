<?php

namespace Jupitern\Slim3\ServiceProviders;

interface ProviderInterface
{
	public static function register(string $serviceName, array $settings = []);
}