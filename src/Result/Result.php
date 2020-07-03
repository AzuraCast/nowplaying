<?php
namespace NowPlaying\Result;

final class Result
{
    public CurrentSong $currentSong;

    public Listeners $listeners;

    public Meta $meta;

    public ?array $clients = null;

    public function toArray(): array
    {
        try {
            return json_decode(json_encode($this, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
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
        $currentListeners = $dest->listeners->current + $source->listeners->current;
        $uniqueListeners = $dest->listeners->unique + $source->listeners->unique;
        $dest->listeners = new Listeners($currentListeners, $uniqueListeners);

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

    public static function blank(): self
    {
        $return = new self;
        $return->currentSong = new CurrentSong;
        $return->listeners = new Listeners;
        $return->meta = new Meta;

        return $return;
    }

    public function __clone()
    {
        $this->listeners = clone $this->listeners;
        $this->currentSong = clone $this->currentSong;
        $this->meta = clone $this->meta;
    }
}
