<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use GuzzleHttp\Promise\PromiseInterface;
use JsonException;
use NowPlaying\Result\Client;
use NowPlaying\Result\CurrentSong;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Meta;
use NowPlaying\Result\Result;

final class Shoutcast2 extends AdapterAbstract
{
    public function getNowPlayingAsync(?string $mount = null, bool $includeClients = false): PromiseInterface
    {
        if (null !== $this->adminPassword) {
            $promises = [
                self::PROMISE_NOW_PLAYING => $this->getAdminNowPlaying($mount)->then(
                    fn(?Result $result) => $result ?? $this->getPublicNowPlaying($mount)
                )
            ];

            if ($includeClients) {
                $promises[self::PROMISE_CLIENTS] = $this->getClientsAsync($mount, true);
            }

            return $this->assembleNowPlayingResult($promises);
        }

        return $this->getPublicNowPlaying($mount)->then(
            fn(?Result $result) => $result ?? Result::blank()
        );
    }

    private function getAdminNowPlaying(?string $mount): PromiseInterface
    {
        $query = [];
        if (!empty($mount)) {
            $query['sid'] = $mount;
        }

        $query['mode'] = 'viewxml';
        $query['page'] = '7';

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery('/admin.cgi', $query)
        );

        return $this->getUrl($request)->then(
            fn(?string $payload) => $this->handleNowPlayingPayload($payload)
        );
    }

    private function getPublicNowPlaying(?string $mount): PromiseInterface
    {
        $query = [];
        if (!empty($mount)) {
            $query['sid'] = $mount;
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery('/stats', $query)
        );

        return $this->getUrl($request)->then(
            fn(?string $payload) => $this->handleNowPlayingPayload($payload)
        );
    }

    private function handleNowPlayingPayload(?string $payload): ?Result
    {
        if (empty($payload)) {
            return null;
        }

        $xml = $this->getSimpleXml($payload);
        if (null === $xml) {
            return null;
        }

        // Fix ShoutCast 2 bug where 3 spaces = " - "
        $currentSongText = (string)$xml->SONGTITLE;
        $currentSongText = str_replace('   ', ' - ', $currentSongText);

        $np = new Result;
        $np->currentSong = new CurrentSong(
            text: $currentSongText,
            delimiter: '-'
        );
        $np->listeners = new Listeners(
            (int)$xml->CURRENTLISTENERS,
            (int)$xml->UNIQUELISTENERS
        );
        $np->meta = new Meta(
            !empty($np->currentSong->text),
            (int)$xml->BITRATE,
            (string)$xml->CONTENT
        );

        return $np;
    }

    public function getClientsAsync(?string $mount = null, bool $uniqueOnly = true): PromiseInterface
    {
        $query = [
            'sid' => (empty($mount)) ? 1 : $mount,
            'mode' => 'viewjson',
            'page' => 3,
        ];

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath(
                rtrim($this->baseUri->getPath(), '/').'/admin.cgi'
            )->withQuery(http_build_query($query))
        );

        return $this->getUrl($request)->then(
            function(?string $return_raw) use ($mount, $uniqueOnly) {
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
        );
    }
}
