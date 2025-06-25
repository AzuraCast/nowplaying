<?php

declare(strict_types=1);

namespace NowPlaying\Result;

final class CurrentSong
{
    public string $text;

    public string $title;

    public string $artist;

    public string $album;

    public function __construct(
        string $text = '',
        string $title = '',
        string $artist = '',
        string $album = '',
        string $delimiter = ' - '
    ) {
        $text = $this->cleanUpString($text);
        $title = $this->cleanUpString($title);
        $artist = $this->cleanUpString($artist);
        $album = $this->cleanUpString($album);

        if (empty($text) && (!empty($title) || !empty($artist))) {
            $textParts = [$artist, $title];
            $text = implode(' - ', array_filter($textParts));
        }

        if (!empty($text) && (empty($title) || empty($artist) || empty($album))) {
            if (str_contains($text, $delimiter)) {
                /** @var non-empty-string $delimiter */
                $stringParts = explode($delimiter, $text);

                if (count($stringParts) >= 2) {
                    $artist = array_shift($stringParts);
                }
                if (count($stringParts) >= 2) {
                    $album = array_shift($stringParts);
                }

                $title = implode($delimiter, $stringParts);
            } else {
                $title = $text;
            }
        }

        $this->text = $text;
        $this->title = $title;
        $this->artist = $artist;
        $this->album = $album;
    }

    protected function cleanUpString(string $value): string
    {
        $value = htmlspecialchars_decode($value);
        return trim($value, " \t\n\r\0\x0B-");
    }
}
