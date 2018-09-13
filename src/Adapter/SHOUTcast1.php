<?php
namespace NowPlaying\Adapter;

use NowPlaying\Exception;

class SHOUTcast1 extends AdapterAbstract
{
    /**
     * @inheritdoc
     */
    public function getNowPlaying($mount = null, $payload = null): array
    {
        $return_raw = $this->getUrl($this->getBaseUrl()->withPath('/7.html'));

        if (empty($return_raw)) {
            throw new Exception('Remote server returned empty response.');
        }

        preg_match("/<body.*>(.*)<\/body>/smU", $return_raw, $return);
        [$current_listeners, , , , $unique_listeners, $bitrate, $title] = explode(',', $return[1], 7);

        // Increment listener counts in the now playing data.
        $np = self::NOWPLAYING_EMPTY;

        $np['listeners']['current'] += (int)$current_listeners;
        $np['listeners']['unique'] += (int)$unique_listeners;
        $np['listeners']['total'] += $this->getListenerCount((int)$unique_listeners, (int)$current_listeners);

        $np['current_song'] = $this->getSongFromString($title, '-');
        $np['meta']['status'] = !empty($np['current_song']['text'])
            ? 'online'
            : 'offline';
        $np['meta']['bitrate'] = $bitrate;

        return $np;
    }

    /**
     * @inheritdoc
     */
    public function getClients($mount = null): array
    {
        throw new Exception('This feature is not implemented for this adapter.');
    }
}