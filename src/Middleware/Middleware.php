<?php

namespace Servdebt\SlimCore\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface as Request};
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

abstract class Middleware
{
    public function appendToResponse(Request $request, RequestHandler $handler, $contentToAppend): ResponseInterface
    {
        $response = $handler->handle($request);
        $response->getBody()->write($contentToAppend);

        return $response;
    }

    public function prependToResponse(Request $request, RequestHandler $handler, $contentToPrepend): ResponseInterface
    {
        $response = $handler->handle($request);
        $existingContent = (string)$response->getBody();

        $response = new Response();
        $response->getBody()->write($contentToPrepend . $existingContent);

        return $response;
    }

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}