<?php

declare(strict_types=1);

namespace NowPlaying\Result;

final class Listeners
{
    public int $total;

    public ?int $unique;

    public function __construct(
        int $total = 0,
        ?int $unique = null
    ) {
        $this->total = $total;
        $this->unique = $unique;
    }
}
