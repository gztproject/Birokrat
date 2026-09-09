<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User\User;
use App\EventSubscriber\TwoFactorSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TwoFactorSubscriberTest extends TestCase
{
    public function testPendingChallengeBlocksSetupPage(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTotpEnabled')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('app_2fa')->willReturn('/2fa');

        $session = new Session(new MockArraySessionStorage());
        $session->set('2fa_pending', true);
        $request = Request::create('/user/2fa');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        (new TwoFactorSubscriber($security, $urls))->onRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/2fa', $response->getTargetUrl());
    }

    public function testPendingChallengeBlocksHomepage(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTotpEnabled')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('app_2fa')->willReturn('/2fa');

        $session = new Session(new MockArraySessionStorage());
        $session->set('2fa_pending', true);
        $request = Request::create('/');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        (new TwoFactorSubscriber($security, $urls))->onRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/2fa', $response->getTargetUrl());
    }

    public function testChallengePathIsAllowedWhilePending(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTotpEnabled')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $session = new Session(new MockArraySessionStorage());
        $session->set('2fa_pending', true);
        $request = Request::create('/2fa');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        (new TwoFactorSubscriber($security, $this->createMock(UrlGeneratorInterface::class)))->onRequest($event);

        $this->assertNull($event->getResponse());
    }
}
