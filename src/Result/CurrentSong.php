<?php
namespace NowPlaying\Result;

final class CurrentSong
{
    public string $text;

    public string $title;

    public string $artist;

    public function __construct(
        string $text = '',
        string $title = '',
        string $artist = '',
        string $delimiter = '-'
    ) {
        if (empty($text) && (!empty($title) || !empty($artist))) {
            $text = $title.' - '.$artist;
        } else if (!empty($text) && empty($title) && empty($artist)) {
            // Fix ShoutCast 2 bug where 3 spaces = " - "
            $text = str_replace('   ', ' - ', $text);

            // Remove dashes or spaces on both sides of the name.
            $text = trim($text, " \t\n\r\0\x0B-");

            $string_parts = explode($delimiter, $text);

            // If not normally delimited, return "text" only.
            if (\count($string_parts) === 1) {
                $title = $text;
            } else {
                $title = trim(array_pop($string_parts));
                $artist = trim(implode($delimiter, $string_parts));
            }
        }

        $this->text = $text;
        $this->title = $title;
        $this->artist = $artist;
    }
}
