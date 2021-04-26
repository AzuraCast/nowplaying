<?php

declare(strict_types=1);

namespace NowPlaying\Result;

final class Client
{
    public string $uid;

    public string $ip;

    public string $userAgent;

    public int $connectedSeconds;

    public ?string $mount;

    public function __construct(
        string $uid,
        string $ip,
        string $userAgent = '',
        int $connectedSeconds = 0,
        ?string $mount = null
    ) {
        $this->uid = $uid;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->connectedSeconds = $connectedSeconds;
        $this->mount = $mount;
    }
}
