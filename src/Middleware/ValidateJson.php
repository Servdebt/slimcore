<?php

namespace Jupitern\Slim3\Middleware;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};

/**
 * Class ValidateJson
 *
 * Middleware that it will validate request json body
 * @package app\Middleware
 */
class ValidateJson
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $method = $request->getMethod();
        $body = $request->getBody()->getContents();

        $contentType = null;
        if (isset($request->getHeader('Content-Type')[0]))
        	$contentType = $request->getHeader('Content-Type')[0];

        if ($method == 'POST' && $contentType == 'application/json' && !empty($body)) {
            $json = json_decode($body);

            if (json_last_error() != JSON_ERROR_NONE || $json === null) {
                return app()->error(422, "Invalid request. Json message malformed");
            }
        }

        return $next($request, $response);
    }

}
