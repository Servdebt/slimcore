<?php

namespace Servdebt\SlimCore\Middleware;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface as Request};
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class Session extends Middleware
{

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        $settings = app()->getConfig("session", []);

        \Servdebt\SlimCore\Utils\Session::start($settings);

        return $handler->handle($request);
    }

}
