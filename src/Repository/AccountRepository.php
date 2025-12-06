<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * Find all accounts for a specific user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find accounts for a user with pagination
     */
    public function findByUserWithPagination(User $user, int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total accounts for a user
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find account by account number hash
     */
    public function findByAccountNumberHash(string $hash): ?Account
    {
        return $this->createQueryBuilder('a')
            ->where('a.accountNumberHash = :hash')
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if account number hash already exists
     */
    public function existsByAccountNumberHash(string $hash): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.accountNumberHash = :hash')
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find accounts by currency
     */
    public function findByCurrency(string $currency, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.currency = :currency')
            ->setParameter('currency', strtoupper($currency))
            ->orderBy('a.balance', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find accounts with balance above a certain amount
     */
    public function findWithMinBalance(string $minBalance, string $currency = 'INR'): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.balance >= :minBalance')
            ->andWhere('a.currency = :currency')
            ->setParameter('minBalance', $minBalance)
            ->setParameter('currency', $currency)
            ->orderBy('a.balance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total balance for a user across all accounts
     */
    public function getTotalBalanceByUser(User $user, string $currency = 'INR'): string
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.balance) as totalBalance')
            ->where('a.user = :user')
            ->andWhere('a.currency = :currency')
            ->setParameter('user', $user)
            ->setParameter('currency', $currency)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Create a new account
     */
    public function createAccount(User $user, string $accountNumberEnc, string $accountNumberHash, string $currency = 'INR'): Account
    {
        $account = new Account();
        $account->setUser($user)
            ->setAccountNumberEnc($accountNumberEnc)
            ->setAccountNumberHash($accountNumberHash)
            ->setCurrency($currency)
            ->setBalance('0.00');

        $this->getEntityManager()->persist($account);
        $this->getEntityManager()->flush();

        return $account;
    }

    /**
     * Update account balance
     */
    public function updateBalance(Account $account, string $newBalance): void
    {
        $account->setBalance($newBalance);
        $this->getEntityManager()->flush();
    }

    /**
     * Delete account (only if balance is zero)
     */
    public function safeDeleteAccount(Account $account): bool
    {
        if (bccomp($account->getBalance(), '0.00', 2) !== 0) {
            return false; // Cannot delete account with non-zero balance
        }

        $this->getEntityManager()->remove($account);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Search accounts by partial account number hash or user email
     */
    public function searchAccounts(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->where('a.accountNumberHash LIKE :term')
            ->orWhere('u.email LIKE :emailTerm')
            ->orWhere('u.name LIKE :nameTerm')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->setParameter('emailTerm', '%' . $searchTerm . '%')
            ->setParameter('nameTerm', '%' . $searchTerm . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}