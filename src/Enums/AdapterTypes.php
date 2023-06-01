<?php

declare(strict_types=1);

namespace NowPlaying\Enums;

use NowPlaying\Adapter\AdapterInterface;
use NowPlaying\Adapter\Icecast;
use NowPlaying\Adapter\Shoutcast1;
use NowPlaying\Adapter\Shoutcast2;

enum AdapterTypes: string
{
    case Icecast = 'icecast';
    case Shoutcast1 = 'shoutcast1';
    case Shoutcast2 = 'shoutcast2';

    /**
     * @return class-string<AdapterInterface>
     */
    public function getAdapterClass(): string
    {
        return match($this) {
            self::Icecast => Icecast::class,
            self::Shoutcast1 => Shoutcast1::class,
            self::Shoutcast2 => Shoutcast2::class
        };
    }
}
