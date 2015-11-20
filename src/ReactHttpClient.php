<?php

namespace Http\React;

use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\HttpClient\Client;
use React\HttpClient\Factory;
use React\HttpClient\Request;
use React\HttpClient\Response;

use Http\Client\HttpClient;
use Http\Client\Promise;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Psr\Http\Message\RequestInterface;

/**
 * Client for the React promise implementation
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactHttpClient implements HttpClient, HttpAsyncClient
{
    /**
     * React HTTP client
     * @var Client
     */
    private $client;

    /**
     * React event loop
     * @var LoopInterface
     */
    private $loop;

    /**
     * DNS Resolver for React
     * @var Resolver
     */
    private $resolver;

    /**
     * Initialize the React client
     * @param LoopInterface|null $loop     React Event loop
     * @param Resolver           $resolver React async DNS resolver
     */
    public function __construct(LoopInterface $loop, Resolver $resolver, MessageFactory $messageFactory)
    {
        $this->loop = $loop;
        $this->resolver = $resolver;
        $this->messageFactory = $messageFactory;

        $factory = new Factory();
        $this->client = $factory->create($loop, $resolver);
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
        return new ReactPromise(
            $this->buildReactRequest($request),
            $this->loop,
            $this->messageFactory
        );
    }

    /**
     * Build a React request from the PSR7 RequestInterface
     * @param  RequestInterface $request
     * @return Request
     */
    private function buildReactRequest(RequestInterface $request)
    {
        $headers = [];
        foreach( $request->getHeaders() as $name => $value ) {
            $headers[$name] = (is_array($value)?$value[0]:$value);
        }

        $request = $this->client->request(
            $request->getMethod(),
            (string)$request->getUri(),
            $headers,
            $request->getProtocolVersion()
        );

        return $request;
    }
}
