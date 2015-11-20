<?php

namespace Http\React;

use React\EventLoop\LoopInterface;
use React\HttpClient\Request;
use React\HttpClient\Response;
use Http\Client\Promise;
use Http\Client\Exception;
use Http\Message\MessageFactory;
use Http\Client\Exception\HttpException;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Client\Exception\TransferException;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * React promise implementation
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactPromise implements Promise
{
    /**
     * Current promise state
     * @var int
     */
    private $state = Promise::PENDING;

    /**
     * Resolved ResponseInterface
     * @var ResponseInterface
     */
    private $response;

    /**
     * Resolved Exception if the promise was rejected
     * @var \Exception
     */
    private $exception;

    /**
     * Collection of functions called when promise resolved
     * @var array
     */
    private $onFulfilled = [];

    /**
     * Collection of functions to be called when promise resolution failed
     * @var array
     */
    private $onRejected = [];

    /**
     * React event loop
     * @var LoopInterface
     */
    private $loop;

    /**
     * HTTP Message factory
     * @var MessageFactory
     */
    private $messageFactory;

    public function __construct(
        Request $request,
        LoopInterface $loop,
        MessageFactory $messageFactory
    ) {
        $this->messageFactory = $messageFactory;

        $request->on('response', function(Response $response = null) {
            $body = null;
            $response->on('data', function($data) use (&$body) {
                if( $data instanceof StreamInterface ) {
                    $body = $data;
                } else {
                    $body->write($data);
                }
            });
            $response->on('error', function(\Exception $error) {
                $this->reject($error, $request, $response);
            });
            $response->on('end', function(\Exception $error = null) use ($response, &$body) {
                if( null !== $error ) {
                    $this->reject($error, $request, $response);
                } else {
                    $this->fulfill($response, $body);
                }
            });
        });
        $request->on('error', function(\Exception $error) use ($request) {
            $this->reject($error, $request);
        });
        $request->on('end', function(\Exception $error = null, $response = null, $request = null) {
            if( isset($error) ) {
                $this->reject($error, $request, $response);
            }
        });
        $request->end();

        $this->loop = $loop;
    }

    /**
     * {@inheritdoc}
     */
    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null
    ) {
        if( null !== $onFulfilled ) {
            if( Promise::PENDING === $this->getState() ) {
                $this->onFulfilled[] = $onFulfilled;
            } elseif( Promise::FULFILLED === $this->getState() ) {
                $this->response = call_user_func(
                    $onFulfilled,
                    $this->response
                );
            }
        }
        if( null !== $onRejected ) {
            if( Promise::PENDING === $this->getState() ) {
                $this->onRejected[] = $onRejected;
            } else {
                $this->exception = call_user_func(
                    $onRejected,
                    $this->exception
                );
            }
        }

        return $this;
    }

    /**
     * Fulfill the promise if resolution is successful
     * @param  Response        $response React response
     * @param  StreamInterface $body     Body stream
     */
    protected function fulfill(Response $response, StreamInterface $body)
    {
        if( Promise::PENDING !== $this->getState() ) {
            return;
        }

        $this->state = Promise::FULFILLED;

        $this->response = $this->buildPsr7Response($response, $body);
        $this->response = $this->apply($this->onFulfilled, $this->response);
    }

    /**
     * Reject the promise if resolution failed
     * @param  \Exception $exception
     * @return \Exception
     */
    protected function reject(
        \Exception $exception,
        Request $request = null,
        Response $response = null
    ) {
        if( Promise::PENDING !== $this->getState() ) {
            return;
        }

        $this->state = Promise::REJECTED;

        if( isset($request) ) {
            $request = $this->buildPsr7Request($request);
            if( isset($response) ) {
                $this->exception = new HttpException(
                    $exception->getMessage(),
                    $request,
                    $this->buildPsr7Response($response),
                    $exception
                );
            } else {
                $this->exception = new RequestException(
                    $exception->getMessage(),
                    $request,
                    $exception
                );
            }
        } else {
            $this->exception = new TransferException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }

        $this->exception = $this->apply($this->onRejected, $this->exception);
    }

    /**
     * Call functions.
     *
     * @param callable[] $callbacks on fulfill or on reject callback queue
     * @param mixed      $argument  response or exception
     *
     * @return mixed response or exception
     */
    protected function apply(array &$callbacks, $argument)
    {
        while (count($callbacks) > 0) {
            $callback = array_shift($callbacks);
            $argument = call_user_func($callback, $argument);
        }
        return $argument;
    }

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * {@inheritdoc}
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        $this->loop->run();
    }

    /**
     * Build a PSR7 request instance
     * @param  Request              $request
     * @return RequestInterface
     */
    public function buildPsr7Request(Request $request)
    {
        return $this->messageFactory->createRequest( "GET", "" );
    }

    /**
     * Build a PSR7 response instance
     * @param  Response             $response
     * @param  StreamInterface|null $body
     * @return ResponseInterface
     */
    public function buildPsr7Response(
        Response $response,
        StreamInterface $body = null
    ) {
        return $this->messageFactory->createResponse(
            $response->getCode(),
            $response->getReasonPhrase(),
            $response->getHeaders(),
            $body,
            $response->getVersion()
        );
    }
}
