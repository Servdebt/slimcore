<?php

namespace Servdebt\SlimCore\Handlers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

final class NotFound extends \Slim\Handlers\ErrorHandler
{

	/**
	 * @param Request       $request
	 * @param Response      $response
	 *
	 * @return ResponseInterface
	 * @throws \ReflectionException
	 */
    public function __invoke(
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
		if (app()->isConsole()) {
            $response = app()->resolve('response');
            $response->getBody()->write("Error: request does not match any command::method or mandatory params are not properly set\n");
			return $response;
		}

        return app()->error(404, "uri ". $request->getUri()->getPath() ." not found");
	}



}