<?php

declare(strict_types=1);

namespace NowPlaying;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use NowPlaying\Adapter\AdapterAbstract;
use NowPlaying\Adapter\Icecast;
use NowPlaying\Adapter\SHOUTcast1;
use NowPlaying\Adapter\SHOUTcast2;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AdapterFactory
{
    public const ADAPTER_ICECAST = 'icecast';
    public const ADAPTER_SHOUTCAST1 = 'shoutcast1';
    public const ADAPTER_SHOUTCAST2 = 'shoutcast2';

    protected UriFactoryInterface $uriFactory;

    protected RequestFactoryInterface $requestFactory;

    protected ClientInterface $client;

    protected LoggerInterface $logger;

    /**
     * @param UriFactoryInterface|null $uriFactory
     * @param RequestFactoryInterface|null $requestFactory
     * @param ClientInterface|null $client
     * @param LoggerInterface|null $logger
     */
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

        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUrlFactory();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * @param string $adapterType
     * @param string|UriInterface $baseUri
     *
     * @return AdapterAbstract
     */
    public function getAdapter(
        string $adapterType,
        $baseUri
    ): AdapterAbstract {
        if (!($baseUri instanceof UriInterface)) {
            $baseUri = $this->uriFactory->createUri($baseUri);
        }

        $adapterClassLookup = [
            self::ADAPTER_ICECAST => Icecast::class,
            self::ADAPTER_SHOUTCAST1 => SHOUTcast1::class,
            self::ADAPTER_SHOUTCAST2 => SHOUTcast2::class,
        ];

        if (!isset($adapterClassLookup[$adapterType])) {
            throw new \InvalidArgumentException('Invalid adapter provided.');
        }

        /** @var AdapterAbstract $adapterClass */
        $adapterClass = $adapterClassLookup[$adapterType];

        return new $adapterClass(
            $this->requestFactory,
            $this->client,
            $this->logger,
            $baseUri
        );
    }

    /**
     * @param string|UriInterface $baseUri
     *
     * @return AdapterAbstract
     */
    public function getIcecastAdapter($baseUri): AdapterAbstract
    {
        return $this->getAdapter(self::ADAPTER_ICECAST, $baseUri);
    }

    /**
     * @param string|UriInterface $baseUri
     *
     * @return AdapterAbstract
     */
    public function getShoutcast1Adapter($baseUri): AdapterAbstract
    {
        return $this->getAdapter(self::ADAPTER_SHOUTCAST1, $baseUri);
    }

    /**
     * @param string|UriInterface $baseUri
     *
     * @return AdapterAbstract
     */
    public function getShoutcast2Adapter($baseUri): AdapterAbstract
    {
        return $this->getAdapter(self::ADAPTER_SHOUTCAST2, $baseUri);
    }
}
