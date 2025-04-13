<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountFormType;
use App\Service\UserSettingsUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UserSettingsUpdater $userSettingsUpdater,
    ) {
    }

    #[Route('/settings', name: 'app_settings_index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('warning', $this->translator->trans('common.flash.error.login_required'));

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AccountFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $updateResult = $this->userSettingsUpdater->updateSettings($form, $user);
                $this->addUpdateFlashMessages($updateResult);

                // Redirect after processing to follow the Post-Redirect-Get pattern
                return $this->redirectToRoute('app_settings_index');
            }

            // Form was submitted but is invalid
            $this->addFlash('error', $this->translator->trans('common.flash.error.invalid_form'));
        }

        return $this->render('pages/settings/index.html.twig', [
            'accountForm' => $form->createView(),
        ]);
    }

    /**
     * Adds appropriate flash messages based on the result of the settings update operation.
     *
     * @param array{changes_made: bool, email_changed: bool, password_changed: bool, error: bool} $updateResult the results from the UserSettingsUpdater service
     */
    private function addUpdateFlashMessages(array $updateResult): void
    {
        if ($updateResult['error']) {
            $this->addFlash('error', $this->translator->trans('common.flash.error.unexpected_error'));

            return; // Stop if a persistence error occurred
        }

        if (!$updateResult['changes_made']) {
            $this->addFlash('info', $this->translator->trans('settings.flash.info.no_changes'));

            return; // No changes were detected or saved
        }

        // Add specific success messages if changes were successfully saved
        if ($updateResult['email_changed']) {
            $this->addFlash('success', $this->translator->trans('settings.flash.success.email_updated'));
        }
        if ($updateResult['password_changed']) {
            $this->addFlash('success', $this->translator->trans('settings.flash.success.password_updated'));
        }
    }

    #[Route('/settings/change-locale/{locale}', name: 'app_settings_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        $supportedLocalesRaw = $this->getParameter('keyroll.supported_locales');
        $supportedLocales = [];

        // PHPStan believes $supportedLocalesRaw is already string, making is_string redundant.
        // However, getParameter() returns mixed. We keep the check for robustness against
        // potential non-string values (e.g., null/false from config errors) which !== '' doesn't prevent.
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (is_string($supportedLocalesRaw) && $supportedLocalesRaw !== '') {
            $supportedLocales = explode('|', $supportedLocalesRaw);
        }

        // Fall back to default locale if the requested one is not supported
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = $request->getDefaultLocale();
        }

        $request->getSession()->set('_locale', $locale);

        // Redirect back to the previous page if possible and safe
        $referer = $request->headers->get('referer');
        if ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        // Fallback redirect if referer is not available or external
        return $this->redirectToRoute('app_settings_index');
    }
}
