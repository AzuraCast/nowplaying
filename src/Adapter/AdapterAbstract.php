<?php
namespace NowPlaying\Adapter;

use NowPlaying\Exception;
use NowPlaying\Result\Client;
use NowPlaying\Result\Result;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

abstract class AdapterAbstract implements AdapterInterface
{
    protected UriInterface $baseUri;

    protected RequestFactoryInterface $requestFactory;

    protected ClientInterface $client;

    protected ?string $adminPassword = null;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        ClientInterface $client,
        UriInterface $baseUri,
        ?string $adminPassword
    ) {
        $this->baseUri = $baseUri;
        $this->requestFactory = $requestFactory;
        $this->client = $client;
        $this->adminPassword = $adminPassword;
    }

    /**
     * @inheritDoc
     */
    abstract public function getNowPlaying(?string $mount = null, bool $includeClients = false): Result;

    /**
     * @inheritDoc
     */
    abstract public function getClients(?string $mount = null, bool $uniqueOnly = true): array;

    /**
     * Fetch a remote URL.
     *
     * @param RequestInterface $request
     *
     * @return string
     * @throws Exception|ClientExceptionInterface
     */
    protected function getUrl(RequestInterface $request): string
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
                'Basic ' . base64_encode('admin:' . $this->adminPassword)
            );
        }

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(sprintf('Request returned status %d: %s', $response->getStatusCode(),
                $response->getBody()->getContents()));
        }

        return $response->getBody()->getContents();
    }

    /**
     * Given a list of clients, return only ones with unique UserAgent and IP combinations.
     *
     * @param Client[] $clients
     *
     * @return Client[]
     */
    protected function getUniqueListeners($clients): array
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
     * @return \SimpleXMLElement
     * @throws Exception
     */
    protected function getSimpleXml(string $xmlString): \SimpleXMLElement
    {
        $xmlString = html_entity_decode($xmlString);
        $xmlString = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $xmlString);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if (false === $xml) {
            $xml_errors = [];
            foreach (libxml_get_errors() as $error) {
                $xml_errors[] = $error->message;
            }

            libxml_clear_errors();

            throw new Exception('XML parsing errors: ' . implode(', ', $xml_errors));
        }

        return $xml;
    }
}
