<?php
namespace NowPlaying\Adapter;

use NowPlaying\Exception;
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

        if (!empty($this->admin_password)) {
            // If the XML doesn't parse for any reason, fail back to the JSON below.
            try {
                $np = $this->getXmlNowPlaying($mount);
            } catch (Exception $e) {
            }
        }

        if (null === $np) {
            $np = $this->getJsonNowPlaying($mount);
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

    protected function getJsonNowPlaying(?string $mount = null): Result
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/status-json.xsl')
        );

        $payload = $this->getUrl($request);
        if (!$payload) {
            throw new Exception('Remote server returned empty response.');
        }

        $return = @json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (!$return || !isset($return['icestats']['source'])) {
            throw new Exception(sprintf('Invalid response: %s', $payload));
        }

        $sources = $return['icestats']['source'];
        $mounts = key($sources) === 0 ? $sources : [$sources];
        if (count($mounts) === 0) {
            throw new Exception('Remote server has no mount points.');
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

    protected function getXmlNowPlaying(?string $mount = null): Result
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->baseUri->withPath('/admin/stats')
        );

        $payload = $this->getUrl($request);
        if (!$payload) {
            throw new Exception('Remote server returned empty response.');
        }

        $xml = $this->getSimpleXml($payload);

        $mountSelector = (null !== $mount)
            ? '(/icestats/source[@mount=\'' . $mount . '\'])[1]'
            : '(/icestats/source)[1]';

        $mount = $xml->xpath($mountSelector);

        if (empty($mount)) {
            return Result::blank();
        }

        $row = $mount[0];

        $np = new Result;


        $artist = (string)$row->artist;
        $title = (string)$row->title;
        $np->currentSong = new CurrentSong(
            '',
            $artist ?? '',
            $title ?? '',
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
            throw new Exception('This adapter requires a mount point name.');
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
            throw new Exception('Remote server returned an empty response.');
        }

        $xml = $this->getSimpleXml($returnRaw);

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
