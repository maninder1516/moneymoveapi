<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Find refresh token by token string
     */
    public function findByToken(string $token): ?RefreshToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Find valid (non-expired) refresh token by token string
     */
    public function findValidToken(string $token): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all refresh tokens for a specific user
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Find all valid (non-expired) refresh tokens for a specific user
     */
    public function findValidTokensByUser(User $user): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.user = :user')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('rt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete expired tokens
     */
    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all tokens for a specific user
     */
    public function deleteAllTokensForUser(User $user): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete specific token
     */
    public function deleteToken(string $token): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->execute();
    }

    /**
     * Count total refresh tokens
     */
    public function getTotalTokenCount(): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count valid (non-expired) refresh tokens
     */
    public function getValidTokenCount(): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->where('rt.expiresAt > :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find tokens expiring within specified days
     */
    public function findTokensExpiringWithinDays(int $days): array
    {
        $expiryDate = (new \DateTime())->modify("+{$days} days");

        return $this->createQueryBuilder('rt')
            ->where('rt.expiresAt <= :expiryDate')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('expiryDate', $expiryDate)
            ->setParameter('now', new \DateTime())
            ->orderBy('rt.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}