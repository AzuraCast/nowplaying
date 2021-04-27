<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use JsonException;
use NowPlaying\Result\Client;
use NowPlaying\Result\CurrentSong;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Meta;
use NowPlaying\Result\Result;

final class SHOUTcast2 extends AdapterAbstract
{
    public function getNowPlaying(?string $mount = null, bool $includeClients = false): Result
    {
        $query = [];
        if (!empty($mount)) {
            $query['sid'] = $mount;
        }

        if (!empty($this->adminPassword)) {
            $query['mode'] = 'viewxml';
            $query['page'] = '7';

            $request = $this->requestFactory->createRequest(
                'GET',
                $this->baseUri->withPath('/admin.cgi')
                    ->withQuery(http_build_query($query))
            );
        } else {
            $request = $this->requestFactory->createRequest(
                'GET',
                $this->baseUri->withPath('/stats')
                    ->withQuery(http_build_query($query))
            );
        }

        $payload = $this->getUrl($request);
        if (empty($payload)) {
            return Result::blank();
        }

        $xml = $this->getSimpleXml($payload);
        if (null === $xml) {
            return Result::blank();
        }

        // Fix ShoutCast 2 bug where 3 spaces = " - "
        $currentSongText = (string)$xml->SONGTITLE;
        $currentSongText = str_replace('   ', ' - ', $currentSongText);

        $np = new Result;
        $np->currentSong = new CurrentSong($currentSongText);
        $np->listeners = new Listeners(
            (int)$xml->CURRENTLISTENERS,
            (int)$xml->UNIQUELISTENERS
        );
        $np->meta = new Meta(
            !empty($np->currentSong->text),
            (int)$xml->BITRATE,
            (string)$xml->CONTENT
        );

        if ($includeClients && !empty($this->adminPassword)) {
            $np->clients = $this->getClients($mount, true);

            $np->listeners = new Listeners(
                $np->listeners->total,
                count($np->clients)
            );
        }

        return $np;
    }

    public function getClients(?string $mount = null, bool $uniqueOnly = true): array
    {
        $query = [
            'sid' => (empty($mount)) ? 1 : $mount,
            'mode' => 'viewjson',
            'page' => 3,
        ];

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/admin.cgi')
                ->withQuery(http_build_query($query))
        );

        $return_raw = $this->getUrl($request);
        if (empty($return_raw)) {
            return [];
        }

        try {
            $listeners = json_decode($return_raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error(
                sprintf('JSON parsing error: %s', $e->getMessage()),
                [
                    'exception' => $e,
                    'response' => $return_raw,
                ]
            );
            return [];
        }

        $clients = array_map(
            function ($listener) use ($mount) {
                return new Client(
                    (string)$listener['uid'],
                    (string)$listener['xff'] ?: $listener['hostname'],
                    (string)$listener['useragent'],
                    (int)$listener['connecttime'],
                    $mount
                );
            },
            (array)$listeners
        );

        return $uniqueOnly
            ? $this->getUniqueListeners($clients)
            : $clients;
    }
}
