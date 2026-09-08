<?php

namespace App\EventSubscriber;

use App\Entity\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class TwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            KernelEvents::REQUEST => 'onRequest',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || !$user->isTotpEnabled()) {
            $event->getRequest()->getSession()->remove('2fa_pending');

            return;
        }
        $event->getRequest()->getSession()->set('2fa_pending', true);
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/2fa') || str_starts_with($path, '/user/2fa') || $path === '/login' || $path === '/logout' || $path === '/') {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isTotpEnabled()) {
            return;
        }
        if (!$request->getSession()->get('2fa_pending')) {
            return;
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_2fa')));
    }
}
