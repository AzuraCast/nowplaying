<?php
namespace NowPlaying\Adapter;

use JsonException;
use NowPlaying\Result\Client;
use NowPlaying\Result\CurrentSong;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Meta;
use NowPlaying\Result\Result;

final class Icecast extends AdapterAbstract
{
    public function getNowPlaying(?string $mount = null, bool $includeClients = false): Result
    {
        $np = null;

        if (!empty($this->adminPassword)) {
            // If the XML doesn't parse for any reason, fail back to the JSON below.
            $np = $this->getXmlNowPlaying($mount);

            if (null === $np) {
                $this->logger->warning('Could not fetch XML data; falling back to public JSON.');
            }
        }

        if (null === $np) {
            $np = $this->getJsonNowPlaying($mount);
        }

        if (null === $np) {
            return Result::blank();
        }

        if ($includeClients && !empty($this->adminPassword)) {
            $np->clients = $this->getClients($mount, true);

            $np->listeners = new Listeners(
                $np->listeners->current,
                count($np->clients)
            );
        }

        return $np;
    }

    protected function getJsonNowPlaying(?string $mount = null): ?Result
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/status-json.xsl')
        );

        $payload = $this->getUrl($request);
        if (!$payload) {
            return null;
        }

        $payload = str_replace('"title": -', '"title": " - "', $payload);

        try {
            $return = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error(sprintf('JSON parsing error: %s', $e->getMessage()), [
                'response' => $payload,
            ]);
            return null;
        }

        if (!$return || !isset($return['icestats']['source'])) {
            $this->logger->error('Response does not contain a "source" listing; the stream may be hidden or misspelled.',
                [
                    'response' => $payload,
                ]);
            return null;
        }

        $sources = $return['icestats']['source'];
        $mounts = key($sources) === 0 ? $sources : [$sources];
        if (count($mounts) === 0) {
            $this->logger->error('Remote server has no mount points listed.', [
                'response' => $payload,
            ]);
            return null;
        }

        $npReturn = [];
        foreach ($mounts as $row) {
            $np = new Result;
            $np->currentSong = new CurrentSong(
                $row['yp_currently_playing'] ?? '',
                $row['title'] ?? '',
                $row['artist'] ?? '',
                ' - '
            );
            $np->meta = new Meta(
                !empty($np->currentSong->text),
                $row['bitrate'],
                $row['server_type']
            );
            $np->listeners = new Listeners($row['listeners']);

            $mountName = parse_url($row['listenurl'], \PHP_URL_PATH);
            $npReturn[$mountName] = $np;
        }

        if (!empty($mount) && isset($npReturn[$mount])) {
            return $npReturn[$mount];
        }

        $npAggregate = Result::blank();
        foreach ($npReturn as $np) {
            $npAggregate->merge($np);
        }
        return $npAggregate;
    }

    protected function getXmlNowPlaying(?string $mount = null): ?Result
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/admin/stats')
        );

        $payload = $this->getUrl($request);
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
            $this->logger->error('Remote server has no mount points listed.', [
                'response' => $payload,
            ]);
            return null;
        }

        $row = $mount[0];

        $np = new Result;
        $artist = (string)$row->artist;
        $title = (string)$row->title;
        $np->currentSong = new CurrentSong(
            '',
            $title ?? '',
            $artist ?? '',
            ' - '
        );
        $np->meta = new Meta(
            !empty($np->currentSong->text),
            (int)$row->bitrate,
            (string)$row->server_type
        );
        $np->listeners = new Listeners(
            (int)$row->listeners
        );

        return $np;
    }

    public function getClients(?string $mount = null, bool $uniqueOnly = true): array
    {
        if (empty($mount)) {
            $this->logger->error('This adapter requires a mount point name.');
            return [];
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/admin/listclients')
                ->withQuery(http_build_query([
                    'mount' => $mount,
                ]))
        );

        $returnRaw = $this->getUrl($request);
        if (empty($returnRaw)) {
            return [];
        }

        $xml = $this->getSimpleXml($returnRaw);
        if (null === $xml) {
            return [];
        }

        $clients = [];

        if ((int)$xml->source->listeners > 0) {
            foreach ($xml->source->listener as $listener) {
                $clients[] = new Client(
                    (string)$listener->ID,
                    (string)$listener->IP,
                    (string)$listener->UserAgent,
                    (int)$listener->Connected
                );
            }
        }

        return $uniqueOnly
            ? $this->getUniqueListeners($clients)
            : $clients;
    }
}
