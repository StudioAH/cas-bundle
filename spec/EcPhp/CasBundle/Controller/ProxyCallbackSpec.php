<?php

declare(strict_types=1);

namespace spec\EcPhp\CasBundle\Controller;

use EcPhp\CasBundle\Controller\ProxyCallback;
use EcPhp\CasLib\Cas;
use EcPhp\CasLib\Introspection\Introspector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PhpSpec\ObjectBehavior;
use Psr\Log\NullLogger;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpFoundation\Response;

class ProxyCallbackSpec extends ObjectBehavior
{
    public function it_can_be_invoked()
    {
        $serverRequest = new ServerRequest('GET', 'http://app');
        $properties = \spec\EcPhp\CasBundle\Cas::getTestProperties();
        $client = new Psr18Client(\spec\EcPhp\CasBundle\Cas::getHttpClientMock());
        $cache = new ArrayAdapter();
        $logger = new NullLogger();
        $introspector = new Introspector();

        $psr17Factory = new Psr17Factory();

        $cas = new Cas(
            $serverRequest,
            $properties,
            $client,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $cache,
            $logger,
            $introspector
        );

        $httpFoundationFactory = new HttpFoundationFactory();

        $this
            ->__invoke($cas, $httpFoundationFactory)
            ->shouldBeAnInstanceOf(Response::class);

        $this
            ->__invoke($cas, $httpFoundationFactory)
            ->getStatusCode()
            ->shouldReturn(200);

        $serverRequest = new ServerRequest('GET', 'http://local/cas/proxy?pgtIou=pgtIou&pgtId=pgtId');
        $this
            ->__invoke($cas, $httpFoundationFactory)
            ->shouldBeAnInstanceOf(Response::class);
        $this
            ->__invoke($cas->withServerRequest($serverRequest), $httpFoundationFactory)
            ->getStatusCode()
            ->shouldReturn(200);

        $serverRequest = new ServerRequest('GET', 'http://local/cas/proxy?pgtIou=pgtIou');
        $this
            ->__invoke($cas, $httpFoundationFactory)
            ->shouldBeAnInstanceOf(Response::class);
        $this
            ->__invoke($cas->withServerRequest($serverRequest), $httpFoundationFactory)
            ->getStatusCode()
            ->shouldReturn(500);

        $serverRequest = new ServerRequest('GET', 'http://local/cas/proxy?pgtId=pgtId');
        $this
            ->__invoke($cas, $httpFoundationFactory)
            ->shouldBeAnInstanceOf(Response::class);
        $this
            ->__invoke($cas->withServerRequest($serverRequest), $httpFoundationFactory)
            ->getStatusCode()
            ->shouldReturn(500);
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(ProxyCallback::class);
    }
}
