<?php

declare(strict_types=1);

namespace NowPlaying;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use NowPlaying\Adapter\AdapterInterface;
use NowPlaying\Enums\AdapterTypes;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AdapterFactory
{
    protected UriFactoryInterface $uriFactory;

    protected RequestFactoryInterface $requestFactory;

    protected ClientInterface $client;

    protected LoggerInterface $logger;

    public function __construct(
        ?UriFactoryInterface $uriFactory = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?ClientInterface $client = null,
        ?LoggerInterface $logger = null
    ) {
        if ((null === $uriFactory || null === $requestFactory || null === $client)
            && !class_exists(Psr17FactoryDiscovery::class)) {
            throw new \InvalidArgumentException('No auto-discovery mechanism available for PSR factories.');
        }

        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->client = $client ?? new Client();
        $this->logger = $logger ?? new NullLogger;
    }

    public function getAdapter(
        AdapterTypes $adapterType,
        string|UriInterface $baseUri
    ): AdapterInterface {
        if (!($baseUri instanceof UriInterface)) {
            $baseUri = $this->uriFactory->createUri($baseUri);
        }

        $adapterClass = $adapterType->getAdapterClass();
        return new $adapterClass(
            $this->requestFactory,
            $this->client,
            $this->logger,
            $baseUri
        );
    }

    public function getIcecastAdapter(string|UriInterface $baseUri): AdapterInterface
    {
        return $this->getAdapter(
            AdapterTypes::Icecast,
            $baseUri
        );
    }

    public function getShoutcast1Adapter(string|UriInterface $baseUri): AdapterInterface
    {
        return $this->getAdapter(
            AdapterTypes::Shoutcast1,
            $baseUri
        );
    }

    public function getShoutcast2Adapter(string|UriInterface $baseUri): AdapterInterface
    {
        return $this->getAdapter(
            AdapterTypes::Shoutcast2,
            $baseUri
        );
    }
}
