<?php

namespace Servdebt\SlimCore\Handlers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;
use Throwable;

final class Error extends \Slim\Handlers\ErrorHandler
{
    public function __invoke(
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
        $app = app();

        $userInfo = [];
        if ($app->has('user')) {
            $user = $app->resolve('user');
            $userInfo = [
                "id" => is_object($user) ? $user->id ?? $user->UserID ?? $user->userid ?? null : null,
                "username" => is_object($user) ? $user->username ?? $user->Username ?? null : null,
            ];
        }

        $errorCode = $exception instanceof NestedValidationException ? 422 : 500;
        $errorMsg  = $exception->getMessage() ." on ".  $exception->getFile() ." line ". $exception->getLine();
        $messages  = $errorCode === 422 ? $exception->getMessages() : array_slice(preg_split('/\r\n|\r|\n/', $exception->getTraceAsString()), 0, 10);

        // Log the message
        if (isset($this->logger) && $logErrors) {
            $this->logger->error($errorMsg, [
                "trace"     => $messages,
                "user"      => $userInfo,
                "host"      => gethostname(),
                "request"   => array_intersect_key($request->getServerParams(), array_flip([
                    "HTTP_HOST", "SERVER_ADDR", "REMOTE_ADDR", "SERVER_PROTOCOL", "HTTP_CONTENT_LENGTH",
                    "HTTP_USER_AGENT", "REQUEST_METHOD", "REQUEST_URI", "CONTENT_TYPE", "REQUEST_TIME_FLOAT"
                ])),
                // "query" => $request->getQueryParams(),
            ]);
        }

        if ($request->getHeaderLine('Accept') == 'application/json' || !$displayErrorDetails) {
            if (!$displayErrorDetails && $errorCode != 422) {
                $errorMsg = "Ops. An error occurred";
                $messages = [];
            }
            return $app->error($errorCode, $errorMsg, $messages);
        }

        if ($app->isConsole()) {
            if ($app->has('slashtrace')) {
                $slashtrace = $app->resolve('slashtrace');
                $slashtrace->register();
                $slashtrace->handleException($exception);
                return $app->resolve('response')->withStatus($errorCode);
            }

            return $app->consoleError($errorMsg, $messages);
        }

        if ($app->has('slashtrace')) {
            $slashtrace = $app->resolve('slashtrace');
            $slashtrace->register();
            $slashtrace->handleException($exception);
            return $app->resolve('response')->withStatus($errorCode);
        }

        return parent::__invoke($request, $exception, $displayErrorDetails, false, false);
    }

}
