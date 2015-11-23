<?php

namespace Http\React;

use React\Promise\PromiseInterface as ReactPromise;
use Http\Client\Promise;
use Psr\Http\Message\ResponseInterface;

/**
 * React promise adapter implementation
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactPromiseAdapter implements Promise
{
    private $state = Promise::PENDING;

    private $promise;

    private $response;

    private $exception;

    public function __construct(ReactPromise $promise)
    {
        $promise->then(
            function(ResponseInterface $response) {
                $this->state = Promise::FULFILLED;
                $this->response = $response;
            },
            function(\Exception $error) {
                $this->state = Promise::REJECTED;
                $this->exception = $error;
            }
        );
        $this->promise = $promise;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->promise->then(function() use ($onFulfilled) {
            if( null !== $onFulfilled ) {
                call_user_func($onFulfilled, $this->response);
            }
        }, function() use ($onRejected) {
            if( null !== $onRejected ) {
                call_user_func($onRejected, $this->exception);
            }
        });
        return $this;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function wait()
    {}
}
