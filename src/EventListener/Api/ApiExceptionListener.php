<?php

declare(strict_types=1);

namespace App\EventListener\Api;

use App\Exception\Api\UserNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Internal server error';
        $errors = null;

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }

        // Handle specific exceptions
        match (true) {
            $exception instanceof UserNotFoundException => [
                $statusCode = Response::HTTP_NOT_FOUND,
                $message = $exception->getMessage()
            ],
            $exception instanceof NotFoundHttpException => [
                $statusCode = Response::HTTP_NOT_FOUND,
                $message = 'Resource not found'
            ],
            $exception instanceof BadRequestHttpException => [
                $statusCode = Response::HTTP_BAD_REQUEST,
                $message = $exception->getMessage() ?: 'Bad request'
            ],
            default => null
        };

        $response = new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ], $statusCode);

        $event->setResponse($response);
    }
}