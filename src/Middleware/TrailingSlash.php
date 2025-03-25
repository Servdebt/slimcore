<?php

namespace Servdebt\SlimCore\Middleware;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface as Request};
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class TrailingSlash extends Middleware
{

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($path !== '/' && str_ends_with($path, '/')) {

            // recursively remove slashes when it's more than 1 slash
            $path = rtrim($path, '/');

            // permanently redirect paths with a trailing slash
            // to their non-trailing counterpart
            $uri = $uri->withPath($path);

            $request = $request->withUri($uri);
        }

        return $handler->handle($request);
    }

}