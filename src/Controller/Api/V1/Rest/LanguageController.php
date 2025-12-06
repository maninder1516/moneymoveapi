<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Rest;

use App\Controller\Api\V1\BaseV1Controller;
use App\Service\Api\ApiConfigService;
use App\Service\LanguageService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Language demo controller to showcase multilingual API capabilities.
 */
#[Route('/language', name: 'language_')]
class LanguageController extends BaseV1Controller
{
    public function __construct(
        ApiConfigService $apiConfig,
        private readonly LanguageService $languageService
    ) {
        parent::__construct($apiConfig);
    }

    #[Route('/demo', name: 'demo', methods: ['GET'])]
    public function demo(Request $request): JsonResponse
    {
        // Detect language from request
        $detectedLocale = $this->languageService->detectAndSetLocale($request);
        
        return $this->json([
            'success' => true,
            'detected_language' => $detectedLocale,
            'supported_languages' => $this->languageService->getSupportedLocales(),
            'messages' => [
                'account_not_found' => $this->languageService->trans('account.not_found'),
                'user_registered' => $this->languageService->trans('user.registered_successfully'),
                'insufficient_balance' => $this->languageService->trans('account.insufficient_balance'),
                'validation_required' => $this->languageService->trans('validation.amount_required')
            ],
            'usage' => [
                'query_parameter' => 'Add ?lang=es for Spanish, ?lang=fr for French, ?lang=hi for Hindi',
                'header' => 'Use X-Language header: es, fr, hi',
                'accept_language' => 'Use Accept-Language header with supported locales'
            ]
        ]);
    }
}