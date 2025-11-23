<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
readonly class LocaleListener
{
    private const LOCALE_COOKIE_NAME = 'keyroll_locale';
    /**
     * @var list<string>
     */
    private array $supportedLocales;

    public function __construct(
        private string $defaultLocale,
        #[Autowire('%keyroll.supported_locales%')]
        string $supportedLocalesString,
    ) {
        $this->supportedLocales = explode('|', $supportedLocalesString);
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $session = $request->hasSession() ? $request->getSession() : null;
        $locale = null;

        if ($session && $session->has('_locale')) {
            $locale = $session->get('_locale');
        }

        if ($locale === null) {
            $cookieLocale = $request->cookies->get(self::LOCALE_COOKIE_NAME);
            if ($cookieLocale !== null && $this->isSupportedLocale($cookieLocale)) {
                $locale = $cookieLocale;
                if ($session) {
                    $session->set('_locale', $locale);
                }
            }
        }

        $locale ??= $this->defaultLocale;

        $request->setLocale($locale);
    }

    private function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }
}
