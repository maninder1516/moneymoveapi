<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use App\Dto\Api\V1\AccountDto;
use App\Entity\Account;
use App\Service\AccountEncryptionService;

/**
 * Service responsible for transforming Account entities to Data Transfer Objects (DTOs).
 * 
 * This service implements the Transformer pattern to separate data transformation
 * logic from controllers and maintain clean architecture. It handles the conversion
 * of database entities to API-friendly DTOs, including decryption of sensitive data
 * and formatting for JSON responses.
 * 
 * Responsibilities:
 * - Convert Account entities to AccountDto objects
 * - Handle encryption/decryption of sensitive account data
 * - Format data for API consumption
 * - Provide batch transformation methods for collections
 * 
 * Benefits:
 * - Single Responsibility Principle: Only handles data transformation
 * - Reusable: Can be used across multiple controllers and services
 * - Testable: Transformation logic can be unit tested independently
 * - Maintainable: Changes to DTO structure only require updates here
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class AccountDtoTransformerService
{
    /**
     * Encryption service for handling sensitive account data.
     * Used to decrypt account numbers for API responses.
     */
    public function __construct(
        private readonly AccountEncryptionService $encryptionService
    ) {
    }

    /**
     * Transforms a single Account entity into an AccountDto object.
     * 
     * This method handles the conversion of a database Account entity to a 
     * Data Transfer Object suitable for API responses. It includes decryption
     * of sensitive data and extraction of related entity information.
     * 
     * Transformation Process:
     * 1. Extracts basic account information (ID, balance, currency)
     * 2. Decrypts the stored account number for API display
     * 3. Retrieves associated user information
     * 4. Formats timestamps for consistent API responses
     * 
     * @param Account $account The Account entity to transform
     * 
     * @return AccountDto The transformed DTO ready for API serialization
     * 
     * @throws \RuntimeException If account number decryption fails
     * 
     * @example
     * $dto = $transformer->transformToDto($accountEntity);
     * $apiResponse = $dto->toArray();
     */
    public function transformToDto(Account $account): AccountDto
    {
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
    }

    /**
     * Transforms multiple Account entities into an array of AccountDto objects.
     * 
     * This method processes a collection of Account entities and converts each
     * one to its corresponding DTO representation. It's optimized for scenarios
     * where you need to work with DTO objects rather than plain arrays.
     * 
     * Use Cases:
     * - When you need to perform additional operations on DTOs
     * - For type-safe collection handling
     * - When consuming services expect DTO objects
     * 
     * @param Account[] $accounts Array of Account entities to transform
     * 
     * @return AccountDto[] Array of transformed AccountDto objects
     * 
     * @throws \RuntimeException If any account number decryption fails
     * 
     * @example
     * $dtos = $transformer->transformToDtoCollection($accountEntities);
     * foreach ($dtos as $dto) {
     *     // Work with type-safe DTO objects
     *     $balance = $dto->balance;
     * }
     */
    public function transformToDtoCollection(array $accounts): array
    {
        return array_map(
            fn(Account $account) => $this->transformToDto($account),
            $accounts
        );
    }

    /**
     * Transforms Account entities directly to array format for JSON responses.
     * 
     * This method provides a performance-optimized path for converting Account
     * entities directly to arrays suitable for JSON serialization. It combines
     * the transformation and array conversion steps for efficiency.
     * 
     * Ideal for:
     * - API endpoint responses that need arrays
     * - Bulk data export operations
     * - Performance-critical transformations
     * 
     * @param Account[] $accounts Array of Account entities to transform
     * 
     * @return array[] Array of associative arrays ready for JSON encoding
     * 
     * @throws \RuntimeException If any account number decryption fails
     * 
     * @example
     * $responseData = $transformer->transformToArrayCollection($accounts);
     * return new JsonResponse(['data' => $responseData]);
     */
    public function transformToArrayCollection(array $accounts): array
    {
        return array_map(
            fn(Account $account) => $this->transformToDto($account)->toArray(),
            $accounts
        );
    }

    /**
     * Transforms a single Account entity to array format for JSON responses.
     * 
     * This is a convenience method that combines entity-to-DTO transformation
     * with array conversion in a single step. It's perfect for single account
     * API responses where you need the data as an associative array.
     * 
     * @param Account $account The Account entity to transform
     * 
     * @return array Associative array representation ready for JSON encoding
     * 
     * @throws \RuntimeException If account number decryption fails
     * 
     * @example
     * $responseData = $transformer->transformToArray($accountEntity);
     * return new JsonResponse(['data' => $responseData]);
     */
    public function transformToArray(Account $account): array
    {
        return $this->transformToDto($account)->toArray();
    }
}