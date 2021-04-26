<?php

declare(strict_types=1);

namespace NowPlaying\Exception;

use NowPlaying\Exception;
use Throwable;

class UnsupportedException extends Exception
{
    public function __construct(
        string $message = 'This functionality is not supported.',
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
