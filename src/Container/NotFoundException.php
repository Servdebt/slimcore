<?php

namespace Servdebt\SlimCore\Container;

use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}