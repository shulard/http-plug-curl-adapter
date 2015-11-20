<?php

namespace Http\React\Tests;

use Http\Client\Tests\HttpClientTest;
use Http\React\ReactHttpClient;

/**
 * @author Stéphane Hulard <s.hulard@gmail.com>
 */
class ReactHttpClientTest extends HttpClientTest
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
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
