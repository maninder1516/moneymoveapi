<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Api\ApiConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiController extends AbstractController
{
    public function __construct(
        protected readonly ApiConfigService $apiConfig
    ) {
    }
    protected function jsonResponse(
        mixed $data = null,
        int $status = Response::HTTP_OK,
        array $headers = [],
        array $context = []
    ): JsonResponse {
        return $this->json($data, $status, $headers, $context);
    }

    protected function successResponse(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        $format = $this->apiConfig->getResponseFormat();
        return $this->jsonResponse([
            $format['success_field'] => true,
            $format['message_field'] => $message,
            $format['data_field'] => $data
        ]);
    }

    protected function errorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $errors = null): JsonResponse
    {
        $format = $this->apiConfig->getResponseFormat();
        return $this->jsonResponse([
            $format['success_field'] => false,
            $format['message_field'] => $message,
            $format['errors_field'] => $errors
        ], $status);
    }

    protected function paginatedResponse(array $items, int $total, int $page, int $limit): JsonResponse
    {
        $format = $this->apiConfig->getResponseFormat();
        return $this->jsonResponse([
            $format['success_field'] => true,
            $format['data_field'] => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit)
            ]
        ]);
    }
}