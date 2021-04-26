<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use NowPlaying\Exception\UnsupportedException;
use NowPlaying\Result\CurrentSong;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Meta;
use NowPlaying\Result\Result;

final class SHOUTcast1 extends AdapterAbstract
{
    public function getNowPlaying(?string $mount = null, bool $includeClients = false): Result
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/7.html')
        );

        $returnRaw = $this->getUrl($request);
        if (empty($returnRaw)) {
            return Result::blank();
        }

        preg_match("/<body.*>(.*)<\/body>/smU", $returnRaw, $return);
        [$total_listeners, , , , $unique_listeners, $bitrate, $title] = explode(',', $return[1], 7);

        // Increment listener counts in the now playing data.
        $np = new Result;
        $np->currentSong = new CurrentSong($title);
        $np->listeners = new Listeners((int)$total_listeners, (int)$unique_listeners);
        $np->meta = new Meta(
            !empty($np->currentSong->text),
            (int)$bitrate
        );

        return $np;
    }

    public function getClients(?string $mount = null, bool $uniqueOnly = true): array
    {
        throw new UnsupportedException;
    }
}
