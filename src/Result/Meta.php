<?php
namespace NowPlaying\Result;

class Meta
{
    public bool $online;

    public ?int $bitrate;

    public ?string $format;

    public function __construct(
        bool $online = false,
        ?int $bitrate = null,
        ?string $format = null
    ) {
        $this->online = $online;
        $this->bitrate = $bitrate;
        $this->format = $format;
    }
}
