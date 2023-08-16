# NowPlaying

NowPlaying is a lightweight, modern, object-oriented PHP library that abstracts out the currently playing metadata from
popular radio broadcast software into a single common return format.

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
// Example PSR-17 and PSR-18 implementation from Guzzle 7
// Install those with:
//   composer require guzzlehttp/guzzle

$httpFactory = new GuzzleHttp\Psr7\HttpFactory();
$adapterFactory = new NowPlaying\AdapterFactory(
    $httpFactory,
    $httpFactory,
    new GuzzleHttp\Client
);

$adapter = $adapterFactory->getAdapter(
    NowPlaying\Enums\AdapterType::Shoutcast2,
    'http://my-station-url.example.com:8000'
);

// You can also call:
// $adapterFactory->getShoutcast2Adapter('http://url');

// Optionally set administrator password
$adapter->setAdminUsername('admin'); // "admin" is the default
$adapter->setAdminPassword('AdminPassword!');

// The first argument to the functions is the mount point or
// stream ID (SID), to pull one specific stream's information.
$now_playing = $adapter->getNowPlaying('1');

$clients = $adapter->getClients('1');
```

Example "now playing" response (PHP objects represented in JSON):

```json
{
  "currentSong": {
    "text": "Joe Bagale - Until We Meet Again",
    "title": "Until We Meet Again",
    "artist": "Joe Bagale"
  },
  "listeners": {
    "total": 0,
    "unique": 0
  },
  "meta": {
    "online": true,
    "bitrate": 128,
    "format": "audio/mpeg"
  },
  "clients": []
}
```

Example "clients" response:

```json
[
  {
    "uid": 1,
    "ip": "127.0.0.1",
    "userAgent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.81 Safari/537.36",
    "connectedSeconds": 123
  }
]
```
