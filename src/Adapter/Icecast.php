<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use JsonException;
use NowPlaying\Result\Client;
use NowPlaying\Result\CurrentSong;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Meta;
use NowPlaying\Result\Result;

final class Icecast extends AdapterAbstract
{
    public function getNowPlayingAsync(?string $mount = null, bool $includeClients = false): PromiseInterface
    {
        if (null !== $this->adminPassword) {
            $promises = [
                self::PROMISE_NOW_PLAYING => $this->getXmlNowPlaying($mount)->then(
                    fn(?Result $result) => $result ?? $this->getJsonNowPlaying($mount)
                )
            ];

            if ($includeClients) {
                $promises[self::PROMISE_CLIENTS] = $this->getClientsAsync($mount, true);
            }

            return $this->assembleNowPlayingResult($promises);
        }

        return $this->getJsonNowPlaying($mount)->then(
            fn(?Result $result) => $result ?? Result::blank()
        );
    }

    private function getJsonNowPlaying(?string $mount = null): PromiseInterface
    {
        $query = [];
        if (!empty($mount)) {
            $query['mount'] = $mount;
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery('/status-json.xsl', $query)
        );

        return $this->getUrl($request)->then(
            fn(?string $payload) => $this->processJsonNowPlaying($payload, $mount)
        );
    }

    private function processJsonNowPlaying(
        ?string $payload,
        ?string $mount = null
    ): ?Result {
        if (!$payload) {
            return null;
        }

        $payload = str_replace('"title": -', '"title": " - "', $payload);

        try {
            $return = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error(
                sprintf('JSON parsing error: %s', $e->getMessage()),
                [
                    'exception' => $e,
                    'response' => $payload,
                ]
            );
            return null;
        }

        if (!$return || !isset($return['icestats']['source'])) {
            $this->logger->error(
                'Response does not contain a "source" listing; the stream may be hidden or misspelled.',
                [
                    'response' => $payload,
                ]
            );
            return null;
        }

        $sources = $return['icestats']['source'];
        $mounts = key($sources) === 0 ? $sources : [$sources];
        if (count($mounts) === 0) {
            $this->logger->error(
                'Remote server has no mount points listed.',
                [
                    'response' => $payload,
                ]
            );
            return null;
        }

        $npReturn = [];
        foreach ($mounts as $row) {
            $np = new Result;
            $np->currentSong = new CurrentSong(
                $row['yp_currently_playing'] ?? '',
                $row['title'] ?? '',
                $row['artist'] ?? ''
            );

            $bitrate = $row['audio_bitrate'] ?? $row['bitrate'] ?? null;
            $bitrate = (null === $bitrate) ? null : (int)$bitrate;

            $np->meta = new Meta(
                !empty($np->currentSong->text),
                $bitrate,
                $row['server_type']
            );
            $np->listeners = new Listeners((int)$row['listeners']);

            $mountName = parse_url($row['listenurl'], \PHP_URL_PATH);
            $npReturn[$mountName] = $np;
        }

        if (!empty($mount) && isset($npReturn[$mount])) {
            return $npReturn[$mount];
        }

        $npAggregate = Result::blank();
        foreach ($npReturn as $np) {
            $npAggregate = $npAggregate->merge($np);
        }
        return $npAggregate;
    }

    private function getXmlNowPlaying(?string $mount = null): PromiseInterface
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery('/admin/stats')
        );

        return $this->getUrl($request)->then(
            fn(?string $payload) => $this->processXmlNowPlaying($payload, $mount)
        );
    }

    private function processXmlNowPlaying(
        ?string $payload,
        ?string $mount = null
    ): ?Result {
        if (!$payload) {
            return null;
        }

        $xml = $this->getSimpleXml($payload);
        if (null === $xml) {
            return null;
        }

        $mountSelector = (null !== $mount)
            ? '(/icestats/source[@mount=\'' . $mount . '\'])[1]'
            : '(/icestats/source)[1]';

        $mount = $xml->xpath($mountSelector);
        if (empty($mount)) {
            $this->logger->error(
                'Remote server has no mount points listed.',
                [
                    'response' => $payload,
                ]
            );
            return null;
        }

        $row = $mount[0];

        $np = new Result;
        $artist = (string)$row->artist;
        $title = (string)$row->title;
        $np->currentSong = new CurrentSong(
            '',
            $title,
            $artist
        );

        $bitrate = max(
            (int)$row->audio_bitrate,
            (int)$row->bitrate
        );
        $bitrate = (0 === $bitrate) ? null : $bitrate;

        $np->meta = new Meta(
            !empty($np->currentSong->text),
            $bitrate,
            (string)$row->server_type
        );
        $np->listeners = new Listeners(
            (int)$row->listeners
        );

        return $np;
    }

    public function getClientsAsync(?string $mount = null, bool $uniqueOnly = true): PromiseInterface
    {
        if (empty($mount)) {
            $this->logger->error('This adapter requires a mount point name.');
            return Create::promiseFor([]);
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUriWithPathAndQuery(
                '/admin/listclients',
                [
                    'mount' => $mount,
                ]
            )
        );

        return $this->getUrl($request)->then(
            function(?string $returnRaw) use ($mount, $uniqueOnly) {
                if (empty($returnRaw)) {
                    return [];
                }

                $xml = $this->getSimpleXml($returnRaw);
                if (null === $xml) {
                    return [];
                }

                $clients = [];
                foreach ($xml->source->listener as $listener) {
                    $clients[] = new Client(
                        (string)$listener->ID,
                        (string)$listener->IP,
                        (string)$listener->UserAgent,
                        (int)$listener->Connected,
                        $mount
                    );
                }

                return $uniqueOnly
                    ? $this->getUniqueListeners($clients)
                    : $clients;
            }
        );
    }
}
