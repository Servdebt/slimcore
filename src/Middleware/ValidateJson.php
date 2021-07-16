<?php

namespace Servdebt\SlimCore\Middleware;

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface as Request};

/**
 * Class ValidateJson
 *
 * Middleware that it will validate request json body
 * @package app\Middleware
 */
class ValidateJson extends Middleware
{
    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        $body = $request->getBody()->getContents();

        $contentType = $request->getHeader('Content-Type')[0] ?? null;

        if ($contentType === 'application/json' && !empty($body)) {
            $json = json_decode($body);

            if ($json === null || json_last_error() != JSON_ERROR_NONE) {
                return app()->error(422, "Invalid request. Json message malformed");
            }
        }

        return $handler->handle($request);
    }

}
