<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use GuzzleHttp\Promise\PromiseInterface;
use NowPlaying\Result\Client;
use NowPlaying\Result\Result;

interface AdapterInterface
{
    public function setAdminUsername(string $adminUsername): self;

    public function setAdminPassword(?string $adminPassword): self;

    /**
     * Return the current "Now Playing" data for the instance.
     *
     * @param string|null $mount The mount point or stream ID (SID) to fetch.
     * @param bool $includeClients Whether to include client details in the result.
     *
     * @return Result
     */
    public function getNowPlaying(
        ?string $mount = null,
        bool $includeClients = false
    ): Result;

    /**
     * Return a PromiseInterface that resolves to a NowPlaying result if the request is successful.
     *
     * @param string|null $mount The mount point or stream ID (SID) to fetch.
     * @param bool $includeClients Whether to include client details in the result.
     *
     * @return PromiseInterface
     */
    public function getNowPlayingAsync(
        ?string $mount = null,
        bool $includeClients = false
    ): PromiseInterface;

    /**
     * @param string|null $mount
     * @param bool $uniqueOnly
     *
     * @return Client[]
     */
    public function getClients(
        ?string $mount = null,
        bool $uniqueOnly = true
    ): array;

    /**
     * @param string|null $mount
     * @param bool $uniqueOnly
     *
     * @return PromiseInterface
     */
    public function getClientsAsync(
        ?string $mount = null,
        bool $uniqueOnly = true
    ): PromiseInterface;
}
