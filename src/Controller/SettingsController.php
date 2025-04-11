<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/settings', name: 'app_settings_index')]
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedException('User not found or not logged in.');
        }

        /** @var FormInterface<User> $form */
        $form = $this->createForm(AccountFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changesMade = $this->handleAccountUpdate($form, $user);

            if ($changesMade) {
                $this->entityManager->flush();
                // Success messages are added within the helper methods
            } elseif ($form->isSubmitted()) {
                // Add 'no changes' only if submitted and valid but nothing was altered
                $this->addFlash('info', 'settings.flash.info.no_changes');
            }

            return $this->redirectToRoute('app_settings_index');
        }

        return $this->render('pages/settings/index.html.twig', [
            'accountForm' => $form,
        ]);
    }

    private function handleAccountUpdate(FormInterface $form, User $user): bool
    {
        $emailChanged = $this->updateEmailIfChanged($form, $user);
        $passwordChanged = $this->updatePasswordIfProvided($form, $user);

        return $emailChanged || $passwordChanged;
    }

    private function updateEmailIfChanged(FormInterface $form, User $user): bool
    {
        $submittedEmail = $form->get('email')->getData();

        if (!empty($submittedEmail) && $submittedEmail !== $user->getEmail()) {
            $user->setEmail($submittedEmail);
            $this->addFlash('success', 'settings.flash.success.email_updated');

            return true;
        }

        return false;
    }

    private function updatePasswordIfProvided(FormInterface $form, User $user): bool
    {
        $currentPassword = $form->get('currentPassword')->getData();
        $plainPassword = $form->get('plainPassword')->getData();

        if (empty($currentPassword) && empty($plainPassword)) {
            return false;
        }

        if (empty($plainPassword)) {
            $this->addFlash('error', 'settings.flash.error.new_password_required');
            return false;
        }

        if (empty($currentPassword)) {
            $this->addFlash('error', 'settings.flash.error.current_password_required');
            return false;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'settings.flash.error.current_password_incorrect');
            return false;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $this->addFlash('success', 'settings.flash.success.password_updated');

        return true;
    }

    #[Route('/settings/change-locale/{locale}', name: 'app_settings_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        $supportedLocales = $this->getParameter('keyroll.supported_locales');
        if (!in_array($locale, explode('|', $supportedLocales))) {
            $locale = $request->getDefaultLocale();
        }

        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');
        $fallbackUrl = $this->generateUrl('app_settings_index');

        if ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirect($fallbackUrl);
    }
}
