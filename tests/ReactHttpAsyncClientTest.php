<?php

namespace Http\React\Tests;

use Http\Client\Tests\HttpAsyncClientTest;
use Http\React\ReactHttpClient;

/**
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactHttpAsyncClientTest extends HttpAsyncClientTest
{
    /**
     * @return HttpClient
     */
    protected function createHttpAsyncClient()
    {
        $loop = \React\EventLoop\Factory::create();
        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);
        $messageFactory = new \Http\Discovery\MessageFactory\GuzzleFactory();

        return new ReactHttpClient(
            $loop,
            $dnsResolver,
            $messageFactory
        );
    }
}
