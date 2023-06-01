<?php

declare(strict_types=1);

namespace NowPlaying\Result;

final class Client
{
    public function __construct(
        public string $uid,
        public string $ip,
        public string $userAgent = '',
        public int $connectedSeconds = 0,
        public ?string $mount = null
    ) {
    }
}
