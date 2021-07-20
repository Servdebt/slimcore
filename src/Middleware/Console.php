<?php

namespace Servdebt\SlimCore\Middleware;

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface as Request};
use Servdebt\SlimCore\Middleware\Middleware;
use Slim\Psr7\Environment;
use Slim\Psr7\Uri;

class Console extends Middleware
{

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
	{
        if (php_sapi_name() === 'cli') {
            $data = Environment::mock([
                'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
                'REQUEST_URI' => '/command',
                "HTTP_HOST" => '',
            ]);

            $uri = new Uri($data["REQUEST_SCHEME"], $data["HTTP_HOST"], $data["SERVER_PORT"], $data["REQUEST_URI"]);
            $request = $request->withUri($uri);
        }

        return $handler->handle($request);
	}
}
