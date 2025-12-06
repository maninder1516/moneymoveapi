<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service to handle language detection and translation locale setting.
 */
class LanguageService
{
    private array $supportedLocales = ['en', 'es', 'fr', 'hi'];
    private string $defaultLocale = 'en';
    private string $currentLocale;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack
    ) {
        $this->currentLocale = $this->defaultLocale;
    }

    /**
     * Detect and set the locale based on request headers or query parameters.
     */
    public function detectAndSetLocale(?Request $request = null): string
    {
        $request = $request ?? $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return $this->defaultLocale;
        }

        // 1. Check for explicit locale query parameter
        $locale = $request->query->get('lang');
        if ($locale && $this->isLocaleSupported($locale)) {
            $this->currentLocale = $locale;
            return $locale;
        }

        // 2. Check for locale in headers (Accept-Language)
        $preferredLanguage = $request->getPreferredLanguage($this->supportedLocales);
        if ($preferredLanguage && $this->isLocaleSupported($preferredLanguage)) {
            $this->currentLocale = $preferredLanguage;
            return $preferredLanguage;
        }

        // 3. Check for custom language header
        $customLang = $request->headers->get('X-Language');
        if ($customLang && $this->isLocaleSupported($customLang)) {
            $this->currentLocale = $customLang;
            return $customLang;
        }

        // 4. Fall back to default
        $this->currentLocale = $this->defaultLocale;
        return $this->defaultLocale;
    }

    /**
     * Get the current locale.
     */
    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * Get supported locales.
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Check if a locale is supported.
     */
    public function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }

    /**
     * Translate a message with automatic locale detection.
     */
    public function trans(string $id, array $parameters = []): string
    {
        $this->detectAndSetLocale();
        return $this->translator->trans($id, $parameters, null, $this->currentLocale);
    }
}