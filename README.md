# NowPlaying

NowPlaying is a lightweight, modern, object-oriented PHP library that abstracts out the currently playing metadata from popular radio broadcast software into a single common return format.

### Installing

NowPlaying is a Composer package that you can include in your project by running:

```bash
composer require azuracast/nowplaying
```

### Compatibility

|                | Now Playing data | Detailed client information |
|----------------|------------------|-----------------------------|
| Icecast (2.4+) | ✅                | ✅                           |
| SHOUTcast 2    | ✅                | ✅                           |
| SHOUTcast 1    | ✅                | ❌                           |

### Usage Example

```php
<?php
$np = new \NowPlaying\Adapter\SHOUTcast2('http://my-station-url.example.com:8000');

// Some functionality depends on an administrator password, but
// all adapters can pull basic information without it.
$np->setAdminPassword('aBcDeFg123');

// The first argument to the functions is the mount point or
// stream ID (SID), to pull one specific stream's information.
$now_playing = $np->getNowPlaying('1');

$clients = $np->getClients('1');
```

Example "now playing" response:

```
[
    'current_song' => [
        'title' => 'Ticker',
        'artist' => 'Silent Partner',
        'text' => 'Silent Partner - Ticker',
    ],
    'listeners' => [
        'current' => 15,
        'unique' => 8,
        'total' => 15,
    ],
    'meta' => [
        'status' => 'online',
        'bitrate' => 128,
        'format' => 'audio/mpeg',
    ],
]
```

Example "clients" response:

```
[
    0 => [
        'uid' => 1,
        'ip' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.81 Safari/537.36',
        'connected_seconds' => 123,
    ],
]
```