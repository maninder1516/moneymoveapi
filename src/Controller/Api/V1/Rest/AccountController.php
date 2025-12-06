<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Rest;

use App\Controller\Api\V1\BaseV1Controller;
use App\Dto\Api\V1\CreateAccountRequestDto;
use App\Dto\Api\V1\TransferRequestDto;
use App\Entity\User;
use App\Exception\Api\AccountNotFoundException;
use App\Exception\Api\InsufficientBalanceException;
use App\Service\Api\ApiConfigService;
use App\Service\Api\V1\AccountService;
use App\Service\Api\V1\AccountDtoTransformerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API Controller for Account Management Operations.
 * 
 * This controller provides a complete set of RESTful endpoints for managing
 * user accounts in the MoneyMove financial system. It follows clean architecture
 * principles by delegating business logic to services and focusing solely on
 * HTTP request/response handling.
 * 
 * Features:
 * - JWT-based authentication for all endpoints
 * - Comprehensive CRUD operations for accounts
 * - Money transfer functionality with currency conversion
 * - Search and filtering capabilities
 * - Pagination support for large datasets
 * - Proper error handling and validation
 * 
 * Security:
 * - All operations require valid JWT authentication
 * - Users can only access their own accounts
 * - Sensitive data (account numbers) are encrypted at rest
 * - Input validation prevents injection attacks
 * 
 * @Route("/accounts", name="accounts_")
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
#[Route('/accounts', name: 'accounts_')]
class AccountController extends BaseV1Controller
{
    /**
     * Constructor for dependency injection.
     * 
     * @param ApiConfigService $apiConfig Configuration service for API settings
     * @param AccountService $accountService Business logic service for account operations
     * @param AccountDtoTransformerService $dtoTransformer Service for entity-to-DTO transformation
     * @param ValidatorInterface $validator Symfony validator for request validation
     * @param LoggerInterface $logger Logger for debugging and monitoring
     */
    public function __construct(
        ApiConfigService $apiConfig,
        private readonly AccountService $accountService,
        private readonly AccountDtoTransformerService $dtoTransformer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($apiConfig);
    }

    /**
     * List all accounts belonging to the authenticated user.
     * 
     * @Route("", name="list", methods={"GET"})
     * 
     * @param Request $request HTTP request containing pagination parameters
     * @param User $user Currently authenticated user from JWT token
     * 
     * @return JsonResponse Paginated list of user's accounts
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

            $accounts = $this->accountService->getUserAccounts($user, $page, $limit);
            $total = $this->accountService->getUserAccountCount($user);

            $accountsData = $this->dtoTransformer->transformToArrayCollection($accounts);

            return $this->paginatedResponse($accountsData, $total, $page, $limit);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve accounts: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $account = $this->accountService->getUserAccount($user, $id);

            $accountData = $this->dtoTransformer->transformToArray($account);

            return $this->successResponse($accountData);
        } catch (AccountNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve account: ' . $e->getMessage(), 500);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $requestDto = CreateAccountRequestDto::fromArray($data ?? []);

            // Validate request
            $errors = $this->validator->validate($requestDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                $this->logger->warning('Account creation validation failed', [
                    'user_id' => $user->getId(),
                    'errors' => $errorMessages
                ]);
                return $this->errorResponse('Validation failed: ' . implode(', ', $errorMessages), 400);
            }

            // Create account
            $account = $this->accountService->createAccount($user, $requestDto->currency);

            $this->logger->info('Account created successfully', [
                'user_id' => $user->getId(),
                'account_id' => $account->getId(),
                'currency' => $account->getCurrency()
            ]);

            $accountData = $this->dtoTransformer->transformToArray($account);

            return $this->json([
                'success' => true,
                'data' => $accountData
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Account creation failed - Invalid argument', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->error('Account creation failed - Unexpected error', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to create account: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $deleted = $this->accountService->deleteAccount($user, $id);

            if (!$deleted) {
                $this->logger->warning('Account deletion failed - Non-zero balance', [
                    'user_id' => $user->getId(),
                    'account_id' => $id
                ]);
                return $this->errorResponse('Cannot delete account with non-zero balance', 400);
            }

            $this->logger->info('Account deleted successfully', [
                'user_id' => $user->getId(),
                'account_id' => $id
            ]);

            return $this->successResponse(['message' => 'Account deleted successfully']);
        } catch (AccountNotFoundException $e) {
            $this->logger->warning('Account deletion failed - Account not found', [
                'user_id' => $user->getId(),
                'account_id' => $id
            ]);
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            $this->logger->error('Account deletion failed - Unexpected error', [
                'user_id' => $user->getId(),
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to delete account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Transfer money between accounts with automatic currency conversion.
     * 
     * @Route("/{fromId}/transfer", name="transfer", methods={"POST"}, requirements={"fromId"="\d+"})
     * 
     * @param int $fromId ID of the source account (must belong to authenticated user)
     * @param Request $request HTTP request containing transfer details
     * @param User $user Currently authenticated user from JWT token
     * 
     * @return JsonResponse Transfer result with transaction details
     */
    #[Route('/{fromId}/transfer', name: 'transfer', methods: ['POST'], requirements: ['fromId' => '\d+'])]
    public function transfer(int $fromId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $transferId = uniqid('txn_');
        
        $this->logger->info('Transfer initiated', [
            'transfer_id' => $transferId,
            'user_id' => $user->getId(),
            'from_account' => $fromId
        ]);

        try {
            $data = json_decode($request->getContent(), true);
            $transferDto = TransferRequestDto::fromArray($data ?? []);

            // Validate request
            $errors = $this->validator->validate($transferDto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                $this->logger->warning('Transfer validation failed', [
                    'transfer_id' => $transferId,
                    'errors' => $errorMessages
                ]);
                return $this->errorResponse('Validation failed: ' . implode(', ', $errorMessages), 400);
            }
            
            // Get source account (must belong to user)
            $fromAccount = $this->accountService->getUserAccount($user, $fromId);
            
            // Get destination account by account number
            $toAccount = $this->accountService->getAccountByNumber($transferDto->toAccountNumber);

            // Log critical transfer details (ALWAYS logged - financial transaction)
            $this->logger->info('Transfer executing', [
                'transfer_id' => $transferId,
                'from_account' => $fromAccount->getId(),
                'to_account' => $toAccount->getId(),
                'amount' => $transferDto->amount,
                'from_currency' => $fromAccount->getCurrency(),
                'to_currency' => $toAccount->getCurrency(),
                'cross_currency' => $fromAccount->getCurrency() !== $toAccount->getCurrency(),
                'event_type' => 'financial_transaction',
                'compliance' => true
            ]);

            // Perform transfer
            $transferResult = $this->accountService->transferMoney(
                $fromAccount,
                $toAccount,
                $transferDto->amount
            );

            // Log successful completion (ALWAYS logged - regulatory requirement)
            $this->logger->info('Transfer completed', [
                'transfer_id' => $transferId,
                'transaction_id' => $transferResult['transaction_id'],
                'status' => 'success',
                'amount_debited' => $transferDto->amount,
                'amount_credited' => $transferResult['converted_amount'] ?? $transferDto->amount,
                'exchange_rate' => $transferResult['exchange_rate'] ?? null,
                'event_type' => 'financial_transaction',
                'audit_trail' => true,
                'compliance' => true
            ]);

            return $this->successResponse([
                'message' => 'Transfer completed successfully',
                'transfer' => [
                    'transaction_id' => $transferResult['transaction_id'],
                    'from_account' => $fromAccount->getId(),
                    'to_account' => $toAccount->getId(),
                    'amount' => $transferDto->amount,
                    'converted_amount' => $transferResult['converted_amount'],
                    'exchange_rate' => $transferResult['exchange_rate'],
                    'currency' => $fromAccount->getCurrency(),
                    'target_currency' => $transferResult['target_currency'],
                    'status' => $transferResult['status'],
                    'note' => $transferDto->note
                ]
            ]);
        } catch (AccountNotFoundException $e) {
            $this->logger->error('Transfer failed - Account not found', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse($e->getMessage(), 404);
        } catch (InsufficientBalanceException $e) {
            $this->logger->warning('Transfer failed - Insufficient balance', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Transfer failed - Invalid argument', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->logger->critical('Transfer failed - Unexpected error', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->errorResponse('Transfer failed: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/balance/total', name: 'total_balance', methods: ['GET'])]
    public function totalBalance(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $currency = strtoupper($request->query->get('currency', 'INR'));
            $totalBalance = $this->accountService->getUserTotalBalance($user, $currency);

            return $this->successResponse([
                'total_balance' => $totalBalance,
                'currency' => $currency,
                'user_id' => $user->getId()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to calculate total balance: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $searchTerm = $request->query->get('q', '');
            
            if (empty($searchTerm)) {
                return $this->errorResponse('Search term is required', 400);
            }

            $accounts = $this->accountService->searchUserAccounts($user, $searchTerm);

            $accountsData = $this->dtoTransformer->transformToArrayCollection($accounts);

            return $this->successResponse([
                'results' => $accountsData,
                'count' => count($accountsData)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Search failed: ' . $e->getMessage(), 500);
        }
    }
}