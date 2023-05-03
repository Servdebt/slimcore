<?php

namespace Servdebt\SlimCore\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;
use Servdebt\SlimCore\Utils\Session;
use Throwable;

final class Error extends \Slim\Handlers\ErrorHandler
{

    public function __invoke(
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response
    {
        $app = app();

        $userInfo = [];
        try {
            $user = $app->has('user') ? $app->resolve('user') : Session::get('user');
            if ($user) {
                $user = (object)$user;
                $userInfo = [
                    "id"       => $user->id ?? $user->UserID ?? $user->userid ?? null,
                    "username" => $user->username ?? $user->Username ?? null,
                ];
            }
        } catch (\Exception $e) {}

        $errorCode = $exception instanceof NestedValidationException ? 422 : 500;
        $errorMsg = $exception->getMessage() . " on " . $exception->getFile() . " line " . $exception->getLine();
        $messages = $errorCode === 422 ? $exception->getMessages() : array_slice(preg_split('/\r\n|\r|\n/', $exception->getTraceAsString()), 0, 10);

        // Log the message
        if ($this->logger !== null && $logErrors) {
            $this->logger->error($errorMsg, [
                "trace"   => $messages,
                "user"    => $userInfo,
                "host"    => gethostname(),
                "request" => array_intersect_key($request->getServerParams(), array_flip([
                    "HTTP_HOST", "SERVER_ADDR", "REMOTE_ADDR", "SERVER_PROTOCOL", "HTTP_CONTENT_LENGTH",
                    "HTTP_USER_AGENT", "REQUEST_METHOD", "REQUEST_URI", "CONTENT_TYPE", "REQUEST_TIME_FLOAT",
                ])),
                // "query" => $request->getQueryParams(),
            ]);
        }

        if (!$displayErrorDetails && !$app->isConsole()) {
            $errorMsg = $errorCode === 422 ? "Validation error" : $errorMsg;
            if ($errorCode !== 422) {
                $messages = [];
            }
        }

        return app()->error($errorCode, $errorMsg, $messages);
    }

}