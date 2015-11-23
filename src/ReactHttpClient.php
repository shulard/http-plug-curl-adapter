<?php

namespace Http\React;

use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\Promise\Deferred;
use React\HttpClient\Client;
use React\HttpClient\Factory;
use React\HttpClient\Request as ReactRequest;
use React\HttpClient\Response as ReactResponse;

use Http\Client\HttpClient;
use Http\Client\Promise;
use Http\Client\HttpAsyncClient;
use Http\Client\Exception\HttpException;
use Http\Client\Exception\RequestException;
use Http\Message\MessageFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

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
        $requestStream = $this->buildReactRequest($request);
        $deferred = new Deferred();

        $requestStream->on('error', function(\Exception $error) use ($deferred, $request) {
            $deferred->reject(new RequestException(
                $error->getMessage(),
                $request,
                $error
            ));
        });
        $requestStream->on('response', function(ReactResponse $response = null) use ($deferred, $requestStream, $request) {
            $bodyStream = null;
            $response->on('data', function($data) use (&$bodyStream) {
                if( $data instanceof StreamInterface ) {
                    $bodyStream = $data;
                } else {
                    $bodyStream->write($data);
                }
            });

            $response->on('end', function(\Exception $error = null) use ($deferred, $request, $response, &$bodyStream) {
                $bodyStream->rewind();
                $psr7Response = $this->messageFactory->createResponse(
                    $response->getCode(),
                    $response->getReasonPhrase(),
                    $response->getHeaders(),
                    $bodyStream,
                    $response->getVersion()
                );
                if( null !== $error ) {
                    $deferred->reject(new HttpException(
                        $error->getMessage(),
                        $request,
                        $psr7Response,
                        $error
                    ));
                } else {
                    $deferred->resolve($psr7Response);
                }
            });
        });

        $requestStream->end((string)$request->getBody());
        $promise = new ReactPromiseAdapter($deferred->promise());

        $this->loop->run();
        return $promise;
    }

    /**
     * Build a React request from the PSR7 RequestInterface
     * @param  RequestInterface $request
     * @return ReactRequest
     */
    private function buildReactRequest(RequestInterface $request)
    {
        $headers = [];
        foreach( $request->getHeaders() as $name => $value ) {
            $headers[$name] = (is_array($value)?$value[0]:$value);
        }
        if( $request->getBody()->getSize() > 0 ) {
            $headers['Content-Length'] = $request->getBody()->getSize();
        }

        $reactRequest = $this->client->request(
            $request->getMethod(),
            (string)$request->getUri(),
            $headers,
            $request->getProtocolVersion()
        );

        return $reactRequest;
    }
}
