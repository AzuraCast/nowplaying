<?php
namespace NowPlaying\Adapter;

use NowPlaying\Exception;

final class Icecast extends AdapterAbstract
{
    /**
     * @inheritdoc
     */
    public function getNowPlaying($mount = null, $payload = null): array
    {
        if (!empty($payload)) {
            if (strpos($payload, '<') === 0) {
                return $this->_getXmlNowPlaying($payload, $mount);
            }
            return $this->_getJsonNowPlaying($payload, $mount);
        }

        $base_url = $this->getBaseUrl();

        // Use the more reliable administrator statistics page if available.
        if (!empty($this->admin_password)) {
            $payload = $this->getUrl($base_url->withPath('/admin/stats'), [
                'auth' => ['admin', $this->admin_password],
            ]);

            if (!$payload) {
                throw new Exception('Remote server returned empty response.');
            }

            return $this->_getXmlNowPlaying($payload, $mount);
        }

        // Default to using the public JSON feed otherwise.
        $payload = $this->getUrl($base_url->withPath('/status-json.xsl'));

        if (!$payload) {
            throw new Exception('Remote server returned empty response.');
        }

        return $this->_getJsonNowPlaying($payload, $mount);
    }

    /**
     * @param string $payload
     * @param mixed|null $mount
     * @return array
     * @throws Exception
     */
    protected function _getJsonNowPlaying($payload, $mount = null): array
    {
        $return = @json_decode($payload, true);

        if (!$return || !isset($return['icestats']['source'])) {
            throw new Exception(sprintf('Invalid response: %s', $payload));
        }

        $sources = $return['icestats']['source'];
        $mounts = key($sources) === 0 ? $sources : [$sources];
        if (count($mounts) === 0) {
            throw new Exception('Remote server has no mount points.');
        }

        $np_return = [];
        foreach($mounts as $mount_row) {
            $np = self::NOWPLAYING_EMPTY;

            $np['current_song'] = $this->getCurrentSong($mount_row, ' - ');

            $np['meta']['status'] = !empty($np['current_song']['text'])
                ? 'online'
                : 'offline';
            $np['meta']['bitrate'] = $mount_row['bitrate'];
            $np['meta']['format'] = $mount_row['server_type'];

            $np['listeners']['current'] = (int)$mount_row['listeners'];
            $np['listeners']['total'] = (int)$mount_row['listeners'];

            $mount_name = parse_url($mount_row['listenurl'], \PHP_URL_PATH);
            $np_return[$mount_name] = $np;
        }

        if (!empty($mount) && isset($np_return[$mount])) {
            return $np_return[$mount];
        }

        return (count($np_return) > 0)
            ? array_shift($np_return)
            : self::NOWPLAYING_EMPTY;
    }

    /**
     * @param string $payload
     * @param mixed|null $mount
     * @return array
     */
    protected function _getXmlNowPlaying($payload, $mount = null): array
    {
        $xml = $this->getSimpleXml($payload);

        $mount_selector = (null !== $mount)
            ? '(/icestats/source[@mount=\''.$mount.'\'])[1]'
            : '(/icestats/source)[1]';

        $mount = $xml->xpath($mount_selector);

        if (empty($mount)) {
            return self::NOWPLAYING_EMPTY;
        }

        $mount_row = $mount[0];

        $np = self::NOWPLAYING_EMPTY;

        $np['current_song'] = $this->getCurrentSong([
            'artist' => (string)$mount_row->artist,
            'title' => (string)$mount_row->title
        ], ' - ');

        $np['meta']['status'] = !empty($np['current_song']['text'])
            ? 'online'
            : 'offline';
        $np['meta']['bitrate'] = (int)$mount_row->bitrate;
        $np['meta']['format'] = (string)$mount_row->server_type;

        $np['listeners']['current'] = (int)$mount_row->listeners;
        $np['listeners']['total'] = (int)$mount_row->listeners;

        return $np;
    }

    /**
     * @inheritdoc
     */
    public function getClients($mount = null, $unique_only = false): array
    {
        if (empty($mount)) {
            throw new Exception('This adapter requires a mount point name.');
        }

        $return_raw = $this->getUrl($this->getBaseUrl()->withPath('/admin/listclients'), [
            'query' => [
                'mount' => $mount,
            ],
            'auth' => ['admin', $this->getAdminPassword()],
        ]);

        if (empty($return_raw)) {
            throw new Exception('Remote server returned an empty response.');
        }

        $xml = $this->getSimpleXml($return_raw);

        $clients = [];

        if ((int)$xml->source->listeners > 0) {
            foreach($xml->source->listener as $listener) {
                $clients[] = [
                    'uid' => (string)$listener->ID,
                    'ip' => (string)$listener->IP,
                    'user_agent' => (string)$listener->UserAgent,
                    'connected_seconds' => (int)$listener->Connected,
                ];
            }
        }

        return $unique_only
            ? $this->getUniqueListeners($clients)
            : $clients;
    }
}
