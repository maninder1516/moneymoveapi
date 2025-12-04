<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Rest;

use App\Controller\Api\V1\BaseV1Controller;
use App\Service\Api\ApiConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/info', name: 'info_')]
class ApiInfoController extends BaseV1Controller
{
    public function __construct(
        ApiConfigService $apiConfig
    ) {
        parent::__construct($apiConfig);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function info(): JsonResponse
    {
        return $this->successResponse([
            'version' => [
                'current' => $this->apiConfig->getCurrentVersion(),
                'supported' => $this->apiConfig->getSupportedVersions(),
                'deprecation_notice' => $this->apiConfig->getDeprecationNotice(),
            ],
            'rate_limits' => [
                'requests_per_minute' => $this->apiConfig->getRateLimitRequestsPerMinute(),
                'burst_limit' => $this->apiConfig->getRateLimitBurstLimit(),
            ],
            'cors' => $this->apiConfig->getCorsSettings(),
            'response_format' => $this->apiConfig->getResponseFormat(),
        ], 'API information retrieved successfully');
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'healthy',
            'timestamp' => (new \DateTime())->format('c'),
            'version' => $this->apiConfig->getCurrentVersion(),
        ], 'API is healthy');
    }
}