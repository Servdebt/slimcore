<?php

namespace Jupitern\Slim3\Handlers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

final class NotFound extends \Slim\Handlers\NotFound
{

	/**
	 * @param Request       $request
	 * @param Response      $response
	 *
	 * @return ResponseInterface
	 * @throws \ReflectionException
	 */
	public function __invoke(Request $request, Response $response)
	{
		if (app()->isConsole()) {
			return $response->write("Error: request does not match any command::method or mandatory params are not properly set\n");
		}

        return app()->error(404, "uri ". $request->getUri()->getPath() ." not found");
	}



}