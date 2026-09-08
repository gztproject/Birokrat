<?php

namespace App\Controller;

use App\Entity\User\User;
use App\Security\Totp;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TwoFactorController extends AbstractController
{
    #[Route(path: '/2fa', name: 'app_2fa', methods: ['GET', 'POST'])]
    public function challenge(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isTotpEnabled()) {
            $request->getSession()->remove('2fa_pending');

            return $this->redirectToRoute('dashboard_index');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $code = (string) $request->request->get('code', '');
            if (Totp::verify((string) $user->getTotpSecret(), $code)) {
                $request->getSession()->remove('2fa_pending');

                return $this->redirectToRoute('dashboard_index');
            }
            $error = 'Invalid authentication code.';
        }

        return $this->render('security/2fa.html.twig', ['error' => $error]);
    }

    #[Route(path: '/user/2fa', name: 'user_2fa_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request, ManagerRegistry $doctrine): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $error = null;
        if ($request->isMethod('POST') && $request->request->get('disable')) {
            $code = (string) $request->request->get('code', '');
            if (!$user->isTotpEnabled() || !Totp::verify((string) $user->getTotpSecret(), $code)) {
                $error = 'Invalid authentication code.';
            } else {
                $user->disableTotp();
                $doctrine->getManager()->flush();
                $session->remove('2fa_setup_secret');
                $this->addFlash('success', '2FA disabled.');

                return $this->redirectToRoute('user_2fa_setup');
            }
        } elseif ($request->isMethod('POST')) {
            $secret = (string) $session->get('2fa_setup_secret', '');
            $code = (string) $request->request->get('code', '');
            if ($secret !== '' && Totp::verify($secret, $code)) {
                $user->enableTotp($secret);
                $doctrine->getManager()->flush();
                $session->remove('2fa_setup_secret');
                $this->addFlash('success', '2FA enabled.');

                return $this->redirectToRoute('user_2fa_setup');
            }
            $error = 'Invalid authentication code.';
        }

        $secret = (string) $session->get('2fa_setup_secret');
        if ($secret === '') {
            $secret = Totp::generateSecret();
            $session->set('2fa_setup_secret', $secret);
        }

        $uri = Totp::provisioningUri($secret, $user->getUserIdentifier());

        return $this->render('security/2fa_setup.html.twig', [
            'enabled' => $user->isTotpEnabled(),
            'secret' => $secret,
            'uri' => $uri,
            'qr' => Totp::qrCodeDataUri($uri),
            'error' => $error,
        ]);
    }
}
