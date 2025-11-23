<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForceTwoFactorSetupSubscriber implements EventSubscriberInterface
{
    /**
     * @var string[]
     */
    private array $allowedPathPrefixes = [
        '/login',
        '/logout',
        '/register',
        '/2fa',
        '/_wdt',
        '/_profiler',
        '/_error',
        '/assets',
        '/build',
        '/favicon',
        '/translations',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->shouldBypass($event->getRequest()->getPathInfo())) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // In two-factor flow already
        $token = $this->security->getToken();
        if ($token instanceof TwoFactorTokenInterface) {
            return;
        }

        if ($user->isTotpEnabled()) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_2fa_setup')));
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->shouldBypass($event->getRequest()->getPathInfo())) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isTotpEnabled()) {
            return;
        }

        $token = $this->security->getToken();
        if ($token instanceof TwoFactorTokenInterface) {
            return;
        }

        $event->setController(fn () => new RedirectResponse($this->urlGenerator->generate('app_2fa_setup')));
    }

    private function shouldBypass(string $path): bool
    {
        foreach ($this->allowedPathPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return str_starts_with($path, '/_fragment');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::CONTROLLER => ['onKernelController', 512],
        ];
    }
}
