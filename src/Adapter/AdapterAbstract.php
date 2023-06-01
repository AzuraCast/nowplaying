<?php

declare(strict_types=1);

namespace NowPlaying\Adapter;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\RequestOptions;
use NowPlaying\Result\Client;
use NowPlaying\Result\Listeners;
use NowPlaying\Result\Result;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

abstract class AdapterAbstract implements AdapterInterface
{
    protected const PROMISE_NOW_PLAYING = 'np';
    protected const PROMISE_CLIENTS = 'clients';

    protected UriInterface $baseUri;

    protected string $adminUsername = 'admin';

    protected ?string $adminPassword = null;

    public function __construct(
        protected RequestFactoryInterface $requestFactory,
        protected ClientInterface $client,
        protected LoggerInterface $logger,
        UriInterface $baseUri
    ) {
        // Detect a username/password in the base URI itself.
        $uriUserInfo = $baseUri->getUserInfo();
        if ('' !== $uriUserInfo) {
            [$uriUsername, $uriPassword] = explode(':', $uriUserInfo);

            $this->setAdminUsername($uriUsername);
            $this->setAdminPassword($uriPassword);

            $baseUri = $baseUri->withUserInfo('');
        }

        $this->baseUri = $baseUri;
    }

    public function setAdminUsername(string $adminUsername): self
    {
        $this->adminUsername = trim($adminUsername);

        return $this;
    }

    public function setAdminPassword(?string $adminPassword): self
    {
        $adminPassword = trim($adminPassword ?? '');
        $this->adminPassword = !empty($adminPassword) ? $adminPassword : null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getNowPlaying(?string $mount = null, bool $includeClients = false): Result
    {
        return $this->getNowPlayingAsync($mount, $includeClients)->wait();
    }

    /**
     * @inheritDoc
     */
    abstract public function getNowPlayingAsync(
        ?string $mount = null,
        bool $includeClients = false
    ): PromiseInterface;

    /**
     * @inheritDoc
     */
    public function getClients(?string $mount = null, bool $uniqueOnly = true): array
    {
        return $this->getClientsAsync($mount, $uniqueOnly)->wait();
    }

    /**
     * @inheritDoc
     */
    abstract public function getClientsAsync(
        ?string $mount = null,
        bool $uniqueOnly = true
    ): PromiseInterface;

    /**
     * Fetch a remote URL.
     *
     * @param RequestInterface $request
     *
     * @return PromiseInterface
     */
    protected function getUrl(RequestInterface $request): PromiseInterface
    {
        if (!$request->hasHeader('User-Agent')) {
            $request = $request->withHeader(
                'User-Agent',
                'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.2) Gecko/20070219 Firefox/2.0.0.2'
            );
        }

        if (null !== $this->adminPassword) {
            $request = $request->withHeader(
                'Authorization',
                'Basic ' . base64_encode($this->adminUsername . ':' . $this->adminPassword)
            );
        }

        $this->logger->debug(
            sprintf(
                'Sending %s request to %s',
                strtoupper($request->getMethod()),
                $request->getUri()
            )
        );

        return $this->client->sendAsync(
            $request,
            [
                RequestOptions::HTTP_ERRORS => false
            ]
        )->then(
            function(ResponseInterface $response) use ($request) {
                if ($response->getStatusCode() !== 200) {
                    $this->logger->error(
                        sprintf('Request returned status code %d.', $response->getStatusCode()),
                        [
                            'body' => (string)$request->getBody(),
                        ]
                    );
                    return null;
                }

                $body = (string)$response->getBody();

                $this->logger->debug(
                    'Raw body from response.',
                    [
                        'response' => $body,
                    ]
                );

                return $body;
            }
        );
    }

    /**
     * @param array<string, string> $query
     */
    protected function baseUriWithPathAndQuery(
        string $path = '',
        array $query = []
    ): UriInterface {
        $uri = $this->baseUri;

        if ('' !== $path) {
            $uri = $uri->withPath(
                rtrim($uri->getPath(), '/').$path
            );
        }
        if (0 !== count($query)) {
            $uri = $uri->withQuery(http_build_query($query));
        }

        return $uri;
    }

    /**
     * @param PromiseInterface[] $promises
     *
     * @return PromiseInterface
     */
    protected function assembleNowPlayingResult(
        array $promises
    ): PromiseInterface {
        return Utils::settle($promises)->then(
            function ($promises) {
                if (PromiseInterface::REJECTED === $promises[self::PROMISE_NOW_PLAYING]['state']) {
                    $exception = $promises[self::PROMISE_NOW_PLAYING]['reason'] ?? null;

                    if ($exception instanceof \Exception) {
                        $this->logger->error(
                            sprintf(
                                'NowPlaying request encountered an exception: %s',
                                $exception->getMessage()
                            ),
                            [
                                'exception' => $exception
                            ]
                        );
                    }

                    return Result::blank();
                }

                /** @var Result|null $np */
                $np = $promises[self::PROMISE_NOW_PLAYING]['value'] ?? null;

                if (null === $np) {
                    return Result::blank();
                }

                if (isset($promises[self::PROMISE_CLIENTS])) {
                    /** @var Client[]|null $clients */
                    $clients = $promises[self::PROMISE_CLIENTS]['value'] ?? null;

                    if (null !== $clients) {
                        $np->clients = $clients;

                        $np->listeners = new Listeners(
                            $np->listeners->total,
                            count($np->clients)
                        );
                    }
                }

                return $np;
            }
        );
    }

    /**
     * Given a list of clients, return only ones with unique UserAgent and IP combinations.
     *
     * @param Client[] $clients
     *
     * @return Client[]
     */
    protected function getUniqueListeners(array $clients): array
    {
        $uniqueClients = [];
        foreach ($clients as $client) {
            $clientHash = md5($client->ip . $client->userAgent);
            if (!isset($uniqueClients[$clientHash])) {
                $uniqueClients[$clientHash] = $client;
            }
        }

        return array_values($uniqueClients);
    }

    /**
     * Given a raw XML string, sanitize it for invalid characters and parse it with SimpleXML.
     *
     * @param string $xmlString
     *
     * @return SimpleXMLElement|null
     */
    protected function getSimpleXml(string $xmlString): ?SimpleXMLElement
    {
        $xmlString = html_entity_decode($xmlString);
        $xmlString = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $xmlString) ?? '';

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if (false === $xml) {
            $xml_errors = [];
            foreach (libxml_get_errors() as $error) {
                $xml_errors[] = $error->message;
            }

            libxml_clear_errors();

            $this->logger->error(
                'Error parsing XML response.',
                [
                    'response' => $xmlString,
                    'errors' => $xml_errors,
                ]
            );
            return null;
        }

        return $xml;
    }
}
