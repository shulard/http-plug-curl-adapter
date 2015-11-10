<?php

namespace Http\Adapter;

use React\Promise\PromiseInterface;
use Http\Client\Promise;

/**
 * Adapter for the React promise implementation
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactPromise implements Promise
{
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {}

    /**
     * {@inheritdoc}
     */
    public function getState()
    {}

    /**
     * {@inheritdoc}
     */
    public function getResponse()
    {}

    /**
     * {@inheritdoc}
     */
    public function getException()
    {}

    /**
     * {@inheritdoc}
     */
    public function wait()
    {}
}
