<?php
namespace NowPlaying\Adapter;

use NowPlaying\Exception;
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

        if (!empty($this->admin_password)) {
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
            throw new Exception('Remote server returned empty response.');
        }

        $xml = $this->getSimpleXml($payload);

        $np = new Result;
        $np->currentSong = new CurrentSong((string)$xml->SONGTITLE);
        $np->listeners = new Listeners(
            (int)$xml->CURRENTLISTENERS,
            (int)$xml->UNIQUELISTENERS
        );
        $np->meta = new Meta(
            !empty($np->currentSong->text),
            (int)$xml->BITRATE,
            (string)$xml->CONTENT
        );

        if ($includeClients) {
            $np->clients = $this->getClients($mount, true);

            $np->listeners = new Listeners(
                $np->listeners->current,
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
            throw new Exception('Remote server returned empty response.');
        }

        $listeners = json_decode($return_raw, true, 512, JSON_THROW_ON_ERROR);

        $clients = array_map(function ($listener) {
            return new Client(
                $listener['uid'],
                $listener['xff'] ?: $listener['hostname'],
                $listener['useragent'],
                $listener['connecttime']
            );
        }, (array)$listeners);

        return $uniqueOnly
            ? $this->getUniqueListeners($clients)
            : $clients;
    }
}
