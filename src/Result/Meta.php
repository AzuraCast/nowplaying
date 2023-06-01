<?php

declare(strict_types=1);

namespace NowPlaying\Result;

final class Meta
{
    public function __construct(
        public bool $online = false,
        public ?int $bitrate = null,
        public ?string $format = null
    ) {
    }
}
