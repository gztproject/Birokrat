<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LocaleSubscriberTest extends TestCase
{
    public function testStoresRouteLocaleOnTheSession(): void
    {
        $request = $this->requestWithSession();
        $request->attributes->set('_locale', 'en');

        $this->subscriber()->onKernelRequest($this->requestEvent($request));

        $this->assertSame('en', $request->getSession()->get('_locale'));
        $this->assertSame('en', $request->getLocale());
    }

    public function testFallsBackToSessionLocale(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()->set('_locale', 'en');

        $this->subscriber()->onKernelRequest($this->requestEvent($request));

        $this->assertSame('en', $request->getLocale());
    }

    public function testFallsBackToDefaultLocale(): void
    {
        $request = $this->requestWithSession();

        $this->subscriber()->onKernelRequest($this->requestEvent($request));

        $this->assertSame('sl', $request->getLocale());
    }

    private function subscriber(): LocaleSubscriber
    {
        return new LocaleSubscriber($this->createMock(LoggerInterface::class), 'sl');
    }

    private function requestWithSession(): Request
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());

        return $request;
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
