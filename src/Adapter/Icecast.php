<?php
namespace NowPlaying\Adapter;

use DiDom\Document;
use DiDom\Query;
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
        $document = new Document;
        $document->loadXml($payload);

        // Check for a specific source if one is provided.
        $mount_selector = (null !== $mount)
            ? '/icestats/source[@mount=\''.$mount.'\']'
            : '/icestats/source';

        if (!$document->has($mount_selector, Query::TYPE_XPATH)) {
            return self::NOWPLAYING_EMPTY;
        }

        $np = self::NOWPLAYING_EMPTY;
        $mount_row = $document->first($mount_selector, Query::TYPE_XPATH);

        $np['current_song'] = $this->getCurrentSong([
            'artist' => $mount_row->first('./artist', Query::TYPE_XPATH)->text(),
            'title' => $mount_row->first('./title', Query::TYPE_XPATH)->text()
        ], ' - ');

        $np['meta']['status'] = !empty($np['current_song']['text'])
            ? 'online'
            : 'offline';
        $np['meta']['bitrate'] = (int)$mount_row->first('./bitrate', Query::TYPE_XPATH)->text();
        $np['meta']['format'] = $mount_row->first('./server_type', Query::TYPE_XPATH)->text();

        $np['listeners']['current'] = (int)$mount_row->first('./listeners', Query::TYPE_XPATH)->text();
        $np['listeners']['total'] = $np['listeners']['current'];

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

        $payload = $this->getUrl($this->getBaseUrl()->withPath('/admin/listclients'), [
            'query' => [
                'mount' => $mount,
            ],
            'auth' => ['admin', $this->getAdminPassword()],
        ]);

        if (empty($payload)) {
            throw new Exception('Remote server returned an empty response.');
        }

        $document = new Document;
        $document->loadXml($payload);

        $source = $document->first('/icestats/source', Query::TYPE_XPATH);

        if (null === $source || !$source->has('./listener', Query::TYPE_XPATH)) {
            return [];
        }

        $clients = [];
        foreach($source->find('./listener', Query::TYPE_XPATH) as $listener) {
            $clients[] = [
                'uid' => (string)$listener->first('./ID', Query::TYPE_XPATH)->text(),
                'ip' => (string)$listener->first('./IP', Query::TYPE_XPATH)->text(),
                'user_agent' => (string)$listener->first('./UserAgent', Query::TYPE_XPATH)->text(),
                'connected_seconds' => (int)$listener->first('./Connected', Query::TYPE_XPATH)->text(),
            ];
        }

        return $unique_only
            ? $this->getUniqueListeners($clients)
            : $clients;
    }
}
