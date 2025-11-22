<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\TotpSetupType;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/2fa')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GoogleAuthenticatorInterface $googleAuthenticator,
    ) {
    }

    #[Route('/setup', name: 'app_2fa_setup', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function setup(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Ensure the user has a secret to scan
        if ($user->getGoogleAuthenticatorSecret() === null) {
            $user->setTotpSecret($this->googleAuthenticator->generateSecret());
            $user->disableTotp();
            $this->entityManager->flush();
        }

        if ($request->isMethod('POST') && $request->request->has('regenerate_secret')) {
            $user->setTotpSecret($this->googleAuthenticator->generateSecret());
            $user->disableTotp();
            $this->entityManager->flush();
            $this->addFlash('info', 'two_fa.flash.regenerated');

            return $this->redirectToRoute('app_2fa_setup');
        }

        $form = $this->createForm(TotpSetupType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = (string) $form->get('code')->getData();

            if ($this->googleAuthenticator->checkCode($user, $code)) {
                $user->enableTotp();
                $this->entityManager->flush();
                $this->addFlash('success', 'two_fa.flash.enabled');

                return $this->redirectToRoute('app_settings_index');
            }

            $this->addFlash('error', 'two_fa.flash.invalid_code');
        }

        return $this->render('pages/security/two_factor_setup.html.twig', [
            'form' => $form->createView(),
            'qr_content' => $this->googleAuthenticator->getQRContent($user),
            'secret' => $user->getGoogleAuthenticatorSecret(),
            'is_enabled' => $user->isTotpEnabled(),
        ]);
    }
}
