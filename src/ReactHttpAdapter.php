<?php

namespace Http\Adapter;

use React\HttpClient\Client;
use Http\Client\HttpClient;
use Http\Client\HttpAsyncClient;
use Psr\Http\Message\RequestInterface;

/**
 * Adapter for the React promise implementation
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactHttpAdapter implements HttpClient, HttpAsyncClient
{
    /**
     * Adapted react client
     * @var Client
     */
    private $client;

    /**
     * @param Client $react ReactHTTP client
     */
    public function __construct(Client $react)
    {
        $this->client = $react;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request)
    {
        $promise = $this->sendAsyncRequest($request);
        $promise->wait();

        if ($promise->getState() == Promise::REJECTED) {
            throw $promise->getException();
        }

        return $promise->getResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request)
    {
        return new ReactPromise();
    }
}
