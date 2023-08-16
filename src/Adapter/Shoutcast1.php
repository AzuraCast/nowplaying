<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use GuzzleHttp\Promise\PromiseInterface;
use NowPlaying\Exception\UnsupportedException;
use NowPlaying\Result\CurrentSong;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Meta;
use NowPlaying\Result\Result;

final class Shoutcast1 extends AdapterAbstract
{
    public function getNowPlayingAsync(?string $mount = null, bool $includeClients = false): PromiseInterface
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery('/7.html')
        );

        return $this->getUrl($request)->then(
            fn(?string $returnRaw) => $this->processNowPlaying($returnRaw)
        );
    }

    private function processNowPlaying(
        ?string $returnRaw = null
    ): Result {
        if (null === $returnRaw) {
            return Result::blank();
        }

        preg_match("/<body.*>(.*)<\/body>/smU", $returnRaw, $return);
        [$total_listeners, , , , $unique_listeners, $bitrate, $title] = explode(',', $return[1], 7);

        // Increment listener counts in the now playing data.
        $np = new Result;
        $np->currentSong = new CurrentSong(
            text: $title,
            delimiter: '-'
        );
        $np->listeners = new Listeners((int)$total_listeners, (int)$unique_listeners);
        $np->meta = new Meta(
            !empty($np->currentSong->text),
            (int)$bitrate
        );

        return $np;
    }

    public function getClientsAsync(?string $mount = null, bool $uniqueOnly = true): PromiseInterface
    {
        throw new UnsupportedException;
    }
}
