<?php

namespace NowPlaying\Exception;

use NowPlaying\Exception;
use Throwable;

class UnsupportedException extends Exception
{
    public function __construct(
        $message = 'This functionality is not supported.',
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
