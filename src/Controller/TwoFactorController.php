<?php

namespace App\Controller;

use App\Entity\User\User;
use App\Security\Totp;
use App\Security\TotpVerdict;
use App\Security\TotpVerifier;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TwoFactorController extends AbstractController
{
    private const THROTTLED_ERROR = 'Too many failed authentication codes. Try again later.';

    #[Route(path: '/2fa', name: 'app_2fa', methods: ['GET', 'POST'])]
    public function challenge(Request $request, TotpVerifier $verifier, Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isTotpEnabled()) {
            $request->getSession()->remove('2fa_pending');

            return $this->redirectToRoute('dashboard_index');
        }

        $session = $request->getSession();
        $error = null;
        $status = Response::HTTP_OK;
        if ($request->isMethod('POST')) {
            $code = (string) $request->request->get('code', '');
            $verdict = $verifier->verify($user, $code);
            if ($verdict === TotpVerdict::Accepted) {
                $session->remove('2fa_pending');
                $session->remove(TotpVerifier::FAILED_ATTEMPTS_SESSION_KEY);

                return $this->redirectToRoute('dashboard_index');
            }
            if ($verdict === TotpVerdict::Throttled) {
                $error = self::THROTTLED_ERROR;
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($verifier->recordSignInFailure($session)) {
                return $this->endSignIn($security);
            } else {
                $error = 'Invalid authentication code.';
            }
        }

        return $this->render('security/2fa.html.twig', ['error' => $error], new Response(null, $status));
    }

    #[Route(path: '/user/2fa', name: 'user_2fa_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request, ManagerRegistry $doctrine, TotpVerifier $verifier, Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $error = null;
        $status = Response::HTTP_OK;
        if ($request->isMethod('POST') && $request->request->get('disable')) {
            $code = (string) $request->request->get('code', '');
            $verdict = $user->isTotpEnabled()
                ? $verifier->verify($user, $code, static fn (User $locked) => $locked->disableTotp())
                : TotpVerdict::Rejected;
            if ($verdict === TotpVerdict::Accepted) {
                $session->remove('2fa_setup_secret');
                $session->remove(TotpVerifier::FAILED_ATTEMPTS_SESSION_KEY);
                $this->addFlash('success', '2FA disabled.');

                return $this->redirectToRoute('user_2fa_setup');
            }
            if ($verdict === TotpVerdict::Throttled) {
                $error = self::THROTTLED_ERROR;
                $status = Response::HTTP_TOO_MANY_REQUESTS;
            } elseif ($user->isTotpEnabled() && $verifier->recordSignInFailure($session)) {
                return $this->endSignIn($security);
            } else {
                $error = 'Invalid authentication code.';
            }
        } elseif ($request->isMethod('POST')) {
            $secret = (string) $session->get('2fa_setup_secret', '');
            $code = (string) $request->request->get('code', '');
            $slice = $secret !== '' ? Totp::matchingSlice($secret, $code) : null;
            if ($user->isTotpEnabled()) {
                $error = '2FA is already enabled.';
            } elseif ($slice !== null) {
                $user->enableTotp($secret, $slice);
                $doctrine->getManager()->flush();
                $session->remove('2fa_setup_secret');
                $this->addFlash('success', '2FA enabled.');

                return $this->redirectToRoute('user_2fa_setup');
            } else {
                $error = 'Invalid authentication code.';
            }
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
        ], new Response(null, $status));
    }

    /**
     * Ends the sign-in so the next code can only be tried after the password
     * is entered again.
     */
    private function endSignIn(Security $security): Response
    {
        return $security->logout(false) ?? $this->redirectToRoute('app_login');
    }
}
