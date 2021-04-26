<?php

declare(strict_types=1);

namespace NowPlaying\Result;

use JsonException;

final class Result
{
    public CurrentSong $currentSong;

    public Listeners $listeners;

    public Meta $meta;

    /** @var null|Client[] */
    public ?array $clients = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        try {
            return json_decode(json_encode($this, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [];
        }
    }

    public function merge(Result $source): self
    {
        $dest = clone $this;

        // Update track title
        if (empty($dest->currentSong->text) && !empty($source->currentSong->text)) {
            $dest->currentSong = clone $source->currentSong;
        }

        // Sum listeners
        $totalListeners = $dest->listeners->total + $source->listeners->total;

        if (null === $source->listeners->unique) {
            $uniqueListeners = $dest->listeners->unique ?? null;
        } else {
            $uniqueListeners = $source->listeners->unique + ($dest->listeners->unique ?? 0);
        }

        $dest->listeners = new Listeners($totalListeners, $uniqueListeners);

        // Update metadata
        if (!$dest->meta->online && $source->meta->online) {
            $dest->meta->online = true;
        }

        if (empty($dest->meta->format) && !empty($source->meta->format)) {
            $dest->meta->format = $source->meta->format;
        }
        if (empty($dest->meta->bitrate) && !empty($source->meta->bitrate)) {
            $dest->meta->bitrate = $source->meta->bitrate;
        }

        // Merge clients
        if (null !== $source->clients) {
            $clients = $dest->clients ?? [];
            $dest->clients = array_merge($clients, $source->clients);
        }

        return $dest;
    }

    public function __clone()
    {
        $this->listeners = clone $this->listeners;
        $this->currentSong = clone $this->currentSong;
        $this->meta = clone $this->meta;
    }

    public static function blank(): self
    {
        $return = new self;
        $return->currentSong = new CurrentSong;
        $return->listeners = new Listeners;
        $return->meta = new Meta;

        return $return;
    }

    /**
     * @param array<string,mixed> $np
     *
     * @return static
     */

    public static function fromArray(array $np): self
    {
        $result = new self;

        $currentSong = $np['currentSong'] ?? $np['current_song'] ?? [];
        $result->currentSong = new CurrentSong(
            $currentSong['text'] ?? '',
            $currentSong['title'] ?? '',
            $currentSong['artist'] ?? ''
        );

        $listeners = $np['listeners'];
        $result->listeners = new Listeners(
            $listeners['total'] ?? $listeners['current'] ?? 0,
            $listeners['unique'] ?? null
        );

        $meta = $np['meta'];
        $isOnline = (isset($meta['status']))
            ? 'online' === $meta['status']
            : $meta['online'] ?? false;

        $result->meta = new Meta(
            $isOnline,
            $meta['bitrate'] ?? null,
            $meta['format'] ?? null
        );

        if (isset($np['clients'])) {
            $clients = [];

            foreach ($np['clients'] as $row) {
                $clients[] = new Client(
                    $row['uid'],
                    $row['ip'],
                    $row['userAgent'] ?? $row['user_agent'],
                    $row['connectedSeconds'] ?? $row['connected_seconds'] ?? 0
                );
            }

            $result->clients = $clients;
        }

        return $result;
    }
}
