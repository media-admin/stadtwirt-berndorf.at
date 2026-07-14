<?php

namespace FluentCrm\Framework\Container\Contracts;

use Exception;
use FluentCrm\Framework\Container\Contracts\Psr\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
