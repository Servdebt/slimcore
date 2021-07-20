<?php

use function DI\get;

return [
    'request' => get(\Psr\Http\Message\ServerRequestInterface::class),
    'response' => get(\Psr\Http\Message\ResponseInterface::class),
];
