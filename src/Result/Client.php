<?php
namespace NowPlaying\Result;

final class Client
{
    public string $uid;

    public string $ip;

    public string $userAgent;

    public int $connectedSeconds;

    public function __construct(
        string $uid,
        string $ip,
        string $userAgent = '',
        int $connectedSeconds = 0
    ) {
        $this->uid = $uid;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->connectedSeconds = $connectedSeconds;
    }
}
