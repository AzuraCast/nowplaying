<?php

namespace NowPlaying\Result;

final class Listeners
{
    public int $current;

    public int $unique;

    public int $total;

    public function __construct(
        int $current = 0,
        ?int $unique = null,
        ?int $total = null
    ) {
        $this->current = $current;
        $this->unique = $unique ?? $current;

        if (null === $total) {
            $total = ($this->unique === 0 || $this->current === 0)
                ? max($this->unique, $this->current)
                : min($this->unique, $this->current);
        }

        $this->total = $total;
    }
}
