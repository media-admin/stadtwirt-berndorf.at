<?php

namespace FluentCrm\Framework\Container;

use Exception;
use FluentCrm\Framework\Container\Contracts\Psr\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
