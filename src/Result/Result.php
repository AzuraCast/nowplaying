<?php
namespace NowPlaying\Result;

class Result
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


    }

    public static function blank(): self
    {
        $return = new self;
        $return->currentSong = new CurrentSong;
        $return->listeners = new Listeners;
        $return->meta = new Meta;

        return $return;
    }
}
