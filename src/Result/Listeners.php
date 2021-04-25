<?php

namespace NowPlaying\Result;

final class Listeners
{
    public int $current;

    public ?int $unique;

    public function __construct(
        int $current = 0,
        ?int $unique = null
    ) {
        $this->current = $current;
        $this->unique = $unique;
    }
}
