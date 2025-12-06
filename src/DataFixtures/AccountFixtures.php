<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\AccountEncryptionService;
use App\Repository\AccountRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AccountFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly AccountEncryptionService $encryptionService,
        private readonly AccountRepository $accountRepository
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $accountsData = [
             // User 0 (Maninder Kumar) - Multiple accounts
            [
                'userRef' => 'user_0',
                'accounts' => [
                    ['currency' => 'INR', 'balance' => '60000.00'],
                    ['currency' => 'USD', 'balance' => '2000.00'],
                    ['currency' => 'EUR', 'balance' => '1800.00']
                ]
            ],
            // User 1 (John Doe) - Multiple accounts
            [
                'userRef' => 'user_1',
                'accounts' => [
                    ['currency' => 'INR', 'balance' => '50000.00'],
                    ['currency' => 'USD', 'balance' => '1000.00'],
                    ['currency' => 'EUR', 'balance' => '800.00']
                ]
            ],
            // User 2 (Jane Smith) - Business user with high balances
            [
                'userRef' => 'user_2',
                'accounts' => [
                    ['currency' => 'INR', 'balance' => '250000.00'],
                    ['currency' => 'USD', 'balance' => '5000.00'],
                ]
            ],
            // User 3 (Alice Johnson) - Regular user
            [
                'userRef' => 'user_3',
                'accounts' => [
                    ['currency' => 'INR', 'balance' => '15000.00'],
                    ['currency' => 'GBP', 'balance' => '500.00']
                ]
            ],
            // User 4 (Bob Wilson) - New user with minimal balance
            [
                'userRef' => 'user_4',
                'accounts' => [
                    ['currency' => 'INR', 'balance' => '1000.00']
                ]
            ],
            // User 5 (Charlie Brown) - User without username, zero balance accounts
            [
                'userRef' => 'user_5',
                'accounts' => [
                    ['currency' => 'INR', 'balance' => '0.00'],
                    ['currency' => 'USD', 'balance' => '0.00']
                ]
            ]
        ];

        foreach ($accountsData as $userData) {
            /** @var User $user */
            $user = $this->getReference($userData['userRef'], User::class);
            
            foreach ($userData['accounts'] as $accountData) {
                // Generate unique account number
                $accountNumber = $this->generateUniqueAccountNumber($user);
                
                // Encrypt account number
                $encryptionData = $this->encryptionService->encryptAccountNumber($accountNumber);
                
                // Create account
                $account = $this->accountRepository->createAccount(
                    $user,
                    $encryptionData['encrypted'],
                    $encryptionData['hash'],
                    $accountData['currency']
                );
                
                // Set balance if not zero
                if ((float)$accountData['balance'] > 0.00) {
                    $account->setBalance($accountData['balance']);
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    private function generateUniqueAccountNumber(User $user): string
    {
        $maxAttempts = 10;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $accountNumber = $this->encryptionService->generateAccountNumber($user->getId());
            $hash = $this->encryptionService->hashAccountNumber($accountNumber);
            
            if (!$this->accountRepository->existsByAccountNumberHash($hash)) {
                return $accountNumber;
            }
        }

        throw new \RuntimeException('Failed to generate unique account number after multiple attempts');
    }
}