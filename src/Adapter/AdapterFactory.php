<?php
namespace NowPlaying\Adapter;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class AdapterFactory
{
    public const ADAPTER_ICECAST = 'icecast';
    public const ADAPTER_SHOUTCAST1 = 'shoutcast1';
    public const ADAPTER_SHOUTCAST2 = 'shoutcast2';

    protected UriFactoryInterface $uriFactory;

    protected RequestFactoryInterface $requestFactory;

    protected ClientInterface $client;

    /**
     * @param UriFactoryInterface|null $uriFactory
     * @param RequestFactoryInterface|null $requestFactory
     * @param ClientInterface|null $client
     */
    public function __construct(
        ?UriFactoryInterface $uriFactory = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?ClientInterface $client = null
    ) {
        if ((null === $uriFactory || null === $requestFactory || null === $client) && !class_exists(Psr17FactoryDiscovery::class)) {
            throw new \InvalidArgumentException('No auto-discovery mechanism available for PSR factories.');
        }

        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUrlFactory();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->client = $client ?? Psr18ClientDiscovery::find();
    }

    /**
     * @param string $adapterType
     * @param string|UriInterface $baseUri
     * @param string|null $adminPassword
     *
     * @return AdapterAbstract
     */
    public function getAdapter(
        string $adapterType,
        $baseUri,
        string $adminPassword = null
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
            $baseUri,
            $adminPassword
        );
    }
}
