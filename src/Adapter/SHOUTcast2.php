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
use Psr\Http\Message\RequestInterface;

final class SHOUTcast2 extends AdapterAbstract
{
    public function getNowPlayingAsync(?string $mount = null, bool $includeClients = false): PromiseInterface
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
                $this->baseUriWithPathAndQuery('/admin.cgi', $query)
            );

            $promises = [
                self::PROMISE_NOW_PLAYING => $this->processNowPlayingRequest($request)
            ];

            if ($includeClients) {
                $promises[self::PROMISE_CLIENTS] = $this->getClientsAsync($mount, true);
            }

            return $this->assembleNowPlayingResult($promises);
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery('/stats', $query)
        );

        return $this->processNowPlayingRequest($request);
    }

    private function processNowPlayingRequest(
        RequestInterface $request
    ): PromiseInterface {
        return $this->getUrl($request)->then(
            function(?string $payload) {
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

                return $np;
            }
        );
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
