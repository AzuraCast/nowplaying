<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use NowPlaying\Result\Client;
use NowPlaying\Result\Result;

interface AdapterInterface
{
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
     * @param string|null $mount
     * @param bool $uniqueOnly
     *
     * @return Client[]
     */
    public function getClients(
        ?string $mount = null,
        bool $uniqueOnly = true
    ): array;
}
