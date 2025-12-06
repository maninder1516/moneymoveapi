<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'name' => 'Maninder Kumar',
                'email' => 'maninder@example.com',
                'username' => 'maninderkumar',
                'password' => '#Maninder@123'
            ],            
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'username' => 'johndoe',
                'password' => 'password123'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'username' => 'janesmith',
                'password' => 'password123'
            ],
            [
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'username' => 'alicej',
                'password' => 'password123'
            ],
            [
                'name' => 'Bob Wilson',
                'email' => 'bob@example.com',
                'username' => 'bobwilson',
                'password' => 'password123'
            ],
            [
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'username' => null, // Test user without username
                'password' => 'password123'
            ]
        ];

        foreach ($users as $index => $userData) {
            $user = new User();
            $user->setName($userData['name']);
            $user->setEmail($userData['email']);
            
            if ($userData['username']) {
                $user->setUsername($userData['username']);
            }
            
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPasswordHash($hashedPassword);

            $manager->persist($user);
            
            // Add reference for use in other fixtures
            $this->addReference('user_' . $index, $user);
        }

        $manager->flush();
    }
}