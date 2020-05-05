<?php
namespace NowPlaying\Adapter;

use NowPlaying\Exception;

final class SHOUTcast2 extends AdapterAbstract
{
    /**
     * @inheritdoc
     */
    public function getNowPlaying($mount = null, $payload = null): array
    {
        if (empty($payload)) {
            $query = [];
            if (!empty($mount)) {
                $query['sid'] = $mount;
            }

            $baseUrl = $this->getBaseUrl();
            $urlOptions = [
                'query' => $query,
            ];

            if (!empty($this->admin_password)) {
                $url = $baseUrl->withPath('/admin.cgi');
                $urlOptions['query']['mode'] = 'viewxml';
                $urlOptions['query']['page'] = '7';

                $urlOptions['auth'] = ['admin', $this->getAdminPassword()];
            } else {
                $url = $baseUrl->withPath('/stats');
            }

            $payload = $this->getUrl($url, $urlOptions);

            if (empty($payload)) {
                throw new Exception('Remote server returned empty response.');
            }
        }

        $xml = $this->getSimpleXml($payload);

        $np = self::NOWPLAYING_EMPTY;

        // Increment listener counts in the now playing data.
        $u_list = (int)$xml->UNIQUELISTENERS;
        $c_list = (int)$xml->CURRENTLISTENERS;

        $np['current_song'] = $this->getSongFromString((string)$xml->SONGTITLE, '-');

        $np['meta']['status'] = !empty($np['current_song']['text'])
            ? 'online'
            : 'offline';
        $np['meta']['bitrate'] = (int)$xml->BITRATE;
        $np['meta']['format'] = (string)$xml->CONTENT;

        $np['listeners']['current'] = $c_list;
        $np['listeners']['unique'] = $u_list;
        $np['listeners']['total'] += $this->getListenerCount($u_list, $c_list);

        return $np;
    }

    /**
     * @inheritdoc
     */
    public function getClients($mount = null, $unique_only = false): array
    {
        $return_raw = $this->getUrl($this->getBaseUrl()->withPath('/admin.cgi'), [
            'query' => [
                'sid' => (empty($mount)) ? 1 : $mount,
                'mode' => 'viewjson',
                'page' => 3,
            ],
            'auth' => ['admin', $this->getAdminPassword()],
        ]);

        if (empty($return_raw)) {
            throw new Exception('Remote server returned empty response.');
        }

        $listeners = json_decode($return_raw, true);

        $clients = array_map(function($listener) {
            return [
                'uid' => $listener['uid'],
                'ip' => $listener['xff'] ?: $listener['hostname'],
                'user_agent' => $listener['useragent'],
                'connected_seconds' => $listener['connecttime'],
            ];
        }, (array)$listeners);

        return $unique_only
            ? $this->getUniqueListeners($clients)
            : $clients;
    }
}