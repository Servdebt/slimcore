<?php

namespace Jupitern\Slim3\Handlers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Respect\Validation\Exceptions\NestedValidationException;

final class PhpError extends \Slim\Handlers\PhpError
{

	/**
	 * @param Request       $request
	 * @param Response      $response
	 * @param \Throwable    $error
	 *
	 * @return Response
	 * @throws \ReflectionException
	 * @throws \Throwable
	 */
	public function __invoke(Request $request, Response $response, \Throwable $error)
    {
        $app = app();
        
        $userInfo = [];
        if ($app->has('user')) {
            $user = $app->resolve('user');
            $userInfo = [
                "id" => is_object($user) ? $user->id ?? $user->UserID ?? $user->userid ?? null : null,
                "username" => is_object($user) ? $user->username ?? $user->Username ?? null : null,
            ];
        }

        $errorCode  = 500;
        $errorMsg   = $error->getMessage() ." on ".  $error->getFile() ." line ". $error->getLine();
        $messages   = array_slice(preg_split('/\r\n|\r|\n/', $error->getTraceAsString()), 0, 10);

        // Log the message
        if ($app->has('logger')) {
            $app->resolve('logger')->error($errorMsg, [
                "trace"     => $messages,
                "user"      => $userInfo,
                "host"      => gethostname(),
                "request"   => array_intersect_key($request->getServerParams(), array_flip([
                    "HTTP_HOST", "SERVER_ADDR", "REMOTE_ADDR", "SERVER_PROTOCOL", "HTTP_CONTENT_LENGTH",
                    "HTTP_USER_AGENT", "REQUEST_URI", "CONTENT_TYPE", "REQUEST_TIME_FLOAT"
                ])),
                // "query" => $request->getQueryParams(),
            ]);
        }

        if (app()->isConsole()) {
            if (app()->has('slashtrace')) {
                $slashtrace = app()->resolve('slashtrace');
                $slashtrace->register();
                $slashtrace->handleException($error);
                return $response->withStatus($errorCode);
            }

            return app()->consoleError($errorMsg, $messages);
        }

        if ($request->getHeaderLine('Accept') == 'application/json' || !$this->displayErrorDetails) {
            if (!$this->displayErrorDetails) {
                $errorMsg = "Ops. An error occurred";
                $messages = [];
            }

            return app()->error($errorCode, $errorMsg, $messages);
        }

        if (app()->has('slashtrace')) {
            $slashtrace = app()->resolve('slashtrace');
            $slashtrace->register();
            $slashtrace->handleException($error);
            return $response->withStatus($errorCode);
        }

        return parent::__invoke($request, $response, $error);
    }

}