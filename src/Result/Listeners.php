<?php

declare(strict_types=1);

namespace NowPlaying\Result;

final class Listeners
{
    public function __construct(
        public int $total = 0,
        public ?int $unique = null
    ) {
    }
}
