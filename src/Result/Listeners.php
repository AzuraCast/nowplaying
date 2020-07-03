<?php
namespace NowPlaying\Result;

class Listeners
{
    public int $current;

    public int $unique;

    public int $total;

    public function __construct(int $current = 0, int $unique = null, int $total = null)
    {
        $this->current = $current;
        $this->unique = $unique ?? $current;

        if (null === $total) {
            $total = ($unique === 0 || $current === 0)
                ? max($unique, $current)
                : min($unique, $current);
        }

        $this->total = $total;
    }
}
