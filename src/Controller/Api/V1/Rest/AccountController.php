<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Rest;

use App\Controller\Api\V1\BaseV1Controller;
use App\Dto\Api\V1\AccountDto;
use App\Dto\Api\V1\CreateAccountRequestDto;
use App\Dto\Api\V1\TransferRequestDto;
use App\Entity\User;
use App\Exception\Api\AccountNotFoundException;
use App\Exception\Api\InsufficientBalanceException;
use App\Service\Api\ApiConfigService;
use App\Service\Api\V1\AccountService;
use App\Service\AccountEncryptionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/accounts', name: 'accounts_')]
class AccountController extends BaseV1Controller
{
    public function __construct(
        ApiConfigService $apiConfig,
        private readonly AccountService $accountService,
        private readonly AccountEncryptionService $encryptionService,
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct($apiConfig);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

            $accounts = $this->accountService->getUserAccounts($user, $page, $limit);
            $total = $this->accountService->getUserAccountCount($user);

            $accountDtos = array_map(function($account) {
                return new AccountDto(
                    id: $account->getId(),
                    accountNumber: $this->encryptionService->decryptAccountNumber($account->getAccountNumberEnc()),
                    balance: $account->getBalance(),
                    currency: $account->getCurrency(),
                    userId: $account->getUser()->getId(),
                    userName: $account->getUser()->getName(),
                    createdAt: $account->getCreatedAt(),
                    updatedAt: $account->getUpdatedAt()
                );
            }, $accounts);

            return $this->paginatedResponse(
                array_map(fn($dto) => $dto->toArray(), $accountDtos),
                $total,
                $page,
                $limit
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve accounts: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $account = $this->accountService->getUserAccount($user, $id);

            $accountDto = new AccountDto(
                id: $account->getId(),
                accountNumber: $this->encryptionService->decryptAccountNumber($account->getAccountNumberEnc()),
                balance: $account->getBalance(),
                currency: $account->getCurrency(),
                userId: $account->getUser()->getId(),
                userName: $account->getUser()->getName(),
                createdAt: $account->getCreatedAt(),
                updatedAt: $account->getUpdatedAt()
            );

            return $this->successResponse($accountDto->toArray());
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
                return $this->errorResponse('Validation failed: ' . implode(', ', $errorMessages), 400);
            }

            // Create account
            $account = $this->accountService->createAccount($user, $requestDto->currency);

            $accountDto = new AccountDto(
                id: $account->getId(),
                accountNumber: $this->encryptionService->decryptAccountNumber($account->getAccountNumberEnc()),
                balance: $account->getBalance(),
                currency: $account->getCurrency(),
                userId: $account->getUser()->getId(),
                userName: $account->getUser()->getName(),
                createdAt: $account->getCreatedAt(),
                updatedAt: $account->getUpdatedAt()
            );

            return $this->json([
                'success' => true,
                'data' => $accountDto->toArray()
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create account: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $deleted = $this->accountService->deleteAccount($user, $id);

            if (!$deleted) {
                return $this->errorResponse('Cannot delete account with non-zero balance', 400);
            }

            return $this->successResponse(['message' => 'Account deleted successfully']);
        } catch (AccountNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete account: ' . $e->getMessage(), 500);
        }
    }

    #[Route('/{fromId}/transfer', name: 'transfer', methods: ['POST'], requirements: ['fromId' => '\d+'])]
    public function transfer(int $fromId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
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
                return $this->errorResponse('Validation failed: ' . implode(', ', $errorMessages), 400);
            }

            // Get source account (must belong to user)
            $fromAccount = $this->accountService->getUserAccount($user, $fromId);

            // Get destination account by account number
            $toAccount = $this->accountService->getAccountByNumber($transferDto->toAccountNumber);

            // Perform transfer
            $transferResult = $this->accountService->transferMoney(
                $fromAccount,
                $toAccount,
                $transferDto->amount
            );

            return $this->successResponse([
                'message' => 'Transfer completed successfully',
                'transfer' => [
                    'from_account' => $fromAccount->getId(),
                    'to_account' => $toAccount->getId(),
                    'amount' => $transferDto->amount,
                    'currency' => $fromAccount->getCurrency(),
                    'note' => $transferDto->note
                ]
            ]);
        } catch (AccountNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (InsufficientBalanceException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
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

            $accountDtos = array_map(function($account) {
                return new AccountDto(
                    id: $account->getId(),
                    accountNumber: $this->encryptionService->decryptAccountNumber($account->getAccountNumberEnc()),
                    balance: $account->getBalance(),
                    currency: $account->getCurrency(),
                    userId: $account->getUser()->getId(),
                    userName: $account->getUser()->getName(),
                    createdAt: $account->getCreatedAt(),
                    updatedAt: $account->getUpdatedAt()
                );
            }, $accounts);

            return $this->successResponse([
                'results' => array_map(fn($dto) => $dto->toArray(), $accountDtos),
                'count' => count($accountDtos)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Search failed: ' . $e->getMessage(), 500);
        }
    }
}