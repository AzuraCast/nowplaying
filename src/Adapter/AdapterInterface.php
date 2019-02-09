<?php
namespace NowPlaying\Adapter;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use NowPlaying\Exception;

interface AdapterInterface
{
    /**
     * Return the current "Now Playing" data for the instance.
     *
     * @param mixed|null $mount The mount point or stream ID (SID) to fetch.
     * @param string|null $payload A prefetched response from the remote radio station, to avoid
     *                             a duplicate HTTP client request.
     * @return array
     * @throws Exception
     */
    public function getNowPlaying($mount = null, $payload = null): array;

    /**
     * @param Client $http_client
     */
    public function setHttpClient(Client $http_client): void;

    /**
     * @return Client
     * @throws Exception
     */
    public function getHttpClient(): Client;

    /**
     * @param string|Uri $base_url
     */
    public function setBaseUrl($base_url): void;

    /**
     * @return Uri
     * @throws Exception
     */
    public function getBaseUrl(): Uri;

    /**
     * @param string $admin_password
     */
    public function setAdminPassword($admin_password): void;

    /**
     * @return string
     * @throws Exception
     */
    public function getAdminPassword(): string;
}