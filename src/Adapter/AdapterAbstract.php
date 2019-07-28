<?php
namespace NowPlaying\Adapter;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use NowPlaying\Exception;

abstract class AdapterAbstract implements AdapterInterface
{
    public const NOWPLAYING_EMPTY = [
        'current_song' => [
            'text' => 'Stream Offline',
            'title' => '',
            'artist' => '',
        ],
        'listeners' => [
            'current' => 0,
            'unique' => 0,
            'total' => 0,
        ],
        'meta' => [
            'status' => 'offline',
            'bitrate' => 0,
            'format' => '',
        ],
    ];

    /** @var Uri The base URL of the station being managed. */
    protected $base_url;

    /** @var Client */
    protected $http_client;

    /** @var string The administrator password (needed for some functionality). */
    protected $admin_password;

    public function __construct($base_url = null, Client $http_client = null)
    {
        if ($http_client === null) {
            $http_client = new Client([
                'http_errors' => false,
                'timeout' => 3.0,
            ]);
        }

        $this->setHttpClient($http_client);

        if ($base_url !== null) {
            $this->setBaseUrl($base_url);
        }
    }

    /**
     * @param Client $http_client
     */
    public function setHttpClient(Client $http_client): void
    {
        $this->http_client = $http_client;
    }

    /**
     * @return Client
     * @throws Exception
     */
    public function getHttpClient(): Client
    {
        if (!($this->http_client instanceof Client)) {
            throw new Exception('HTTP client has not been set.');
        }

        return $this->http_client;
    }

    /**
     * @param string|Uri $base_url
     */
    public function setBaseUrl($base_url): void
    {
        if (!($base_url instanceof Uri)) {
            $base_url = new Uri($base_url);
        }

        $this->base_url = $base_url;
    }

    /**
     * @return Uri
     * @throws Exception
     */
    public function getBaseUrl(): Uri
    {
        if (!($this->base_url instanceof Uri)) {
            throw new Exception('Base URL has not been set.');
        }

        return $this->base_url;
    }

    /**
     * @param string $admin_password
     */
    public function setAdminPassword($admin_password): void
    {
        $this->admin_password = $admin_password;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getAdminPassword(): string
    {
        if ($this->admin_password === null) {
            throw new Exception('Administrator password not provided.');
        }

        return $this->admin_password;
    }

    /**
     * Return the current "Now Playing" data for the instance.
     *
     * @param mixed|null $mount The mount point or stream ID (SID) to fetch.
     * @param string|null $payload A prefetched response from the remote radio station, to avoid
     *                             a duplicate HTTP client request.
     * @return array
     * @throws Exception
     */
    abstract public function getNowPlaying($mount = null, $payload = null): array;

    /**
     * Return detailed information about the currently connected listeners.
     *
     * @param string|null $mount The mount point (or Stream ID/SID) to pull detailed information about.
     * @param bool $unique_only Whether to group together users with the same IP and user-agent as one "unique" listener.
     * @return array
     * @throws Exception
     */
    abstract public function getClients($mount = null, $unique_only = false): array;

    /**
     * Given a single title or array, compose a "now playing" current song result.
     *
     * @param array|string $raw_data
     * @param string $delimiter
     * @return array
     */
    protected function getCurrentSong($raw_data, $delimiter = ' - '): array
    {
        if (!\is_array($raw_data)) {
            $raw_data = ['title' => $raw_data];
        }

        if (!empty($raw_data['artist'])) {
            return [
                'artist' => $raw_data['artist'],
                'title' => $raw_data['title'],
                'text' => $raw_data['artist'] . ' - ' . $raw_data['title'],
            ];
        }

        return $this->getSongFromString($raw_data['title'], $delimiter);
    }

    /**
     * Return the artist and title from a string in the format "Artist - Title"
     *
     * @param string $song_string
     * @param string $delimiter
     * @return array
     */
    protected function getSongFromString($song_string, $delimiter = '-'): array
    {
        // Fix ShoutCast 2 bug where 3 spaces = " - "
        $song_string = str_replace('   ', ' - ', $song_string);

        // Remove dashes or spaces on both sides of the name.
        $song_string = trim($song_string, " \t\n\r\0\x0B-");

        $string_parts = explode($delimiter, $song_string);

        // If not normally delimited, return "text" only.
        if (\count($string_parts) === 1) {
            return ['text' => $song_string, 'artist' => '', 'title' => $song_string];
        }

        // Title is the last element, artist is all other elements (artists are far more likely to have hyphens).
        $title = trim(array_pop($string_parts));
        $artist = trim(implode($delimiter, $string_parts));

        return [
            'text' => $song_string,
            'artist' => $artist,
            'title' => $title,
        ];
    }

    /**
     * Calculate listener count from unique and current totals.
     *
     * @param int $unique_listeners
     * @param int $current_listeners
     * @return int The likely proper "total" listener count.
     */
    protected function getListenerCount($unique_listeners = 0, $current_listeners = 0): int
    {
        $unique_listeners = (int)$unique_listeners;
        $current_listeners = (int)$current_listeners;

        return ($unique_listeners === 0 || $current_listeners === 0)
            ? max($unique_listeners, $current_listeners)
            : min($unique_listeners, $current_listeners);
    }

    /**
     * Fetch a remote URL.
     *
     * @param string $url
     * @param array|null $options
     * @return string
     * @throws Exception
     */
    protected function getUrl($url, $options = null): string
    {
        $http_client = $this->getHttpClient();

        $options = $options ?? [];

        if (!isset($options['headers']['User-Agent'])) {
            $options['headers']['User-Agent'] = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.2) Gecko/20070219 Firefox/2.0.0.2';
        }

        $response = $http_client->get($url, $options);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf('Request returned status %d: %s', $response->getStatusCode(), $response->getBody()->getContents()));
        }

        return $response->getBody()->getContents();
    }

    /**
     * Given a list of clients, return only ones with unique UserAgent and IP combinations.
     *
     * @param array $clients
     * @return array
     */
    protected function getUniqueListeners($clients): array
    {
        $unique_clients = [];
        foreach($clients as $client) {
            $client_hash = md5($client['ip'].$client['user_agent']);
            if (!isset($unique_clients[$client_hash])) {
                $unique_clients[$client_hash] = $client;
            }
        }

        return array_values($unique_clients);
    }

    /**
     * Given a raw XML string, sanitize it for invalid characters and parse it with SimpleXML.
     *
     * @param string $xmlString
     * @return \SimpleXMLElement
     */
    protected function getSimpleXml($xmlString): \SimpleXMLElement
    {
        $xmlString = html_entity_decode($xmlString);
        $xmlString = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $xmlString);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($sxe === false) {
            $xml_errors = [];
            foreach(libxml_get_errors() as $error) {
                $xml_errors[] = $error->message;
            }

            throw new Exception('XML parsing errors: '.implode(', ', $xml_errors));
        }

        return $xml;
    }
}