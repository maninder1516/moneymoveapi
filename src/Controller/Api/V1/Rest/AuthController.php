<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Rest;

use App\Controller\Api\V1\BaseV1Controller;
use App\Entity\User;
use App\Service\Api\ApiConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/auth', name: 'auth_')]
class AuthController extends BaseV1Controller
{
    public function __construct(
        ApiConfigService $apiConfig,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($apiConfig);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['email'], $data['password'], $data['name'])) {
            return $this->errorResponse(
                $this->translator->trans('user.missing_required_fields', ['%fields%' => 'name, email, password']), 
                400
            );
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);
            
        if ($existingUser) {
            return $this->errorResponse(
                $this->translator->trans('user.email_already_exists'), 
                409
            );
        }

        // Create new user
        $user = new User();
        $user->setName($data['name']);
        $user->setEmail($data['email']);
        
        // Only set username if provided
        if (!empty($data['username'])) {
            $user->setUsername($data['username']);
        }
        
        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPasswordHash($hashedPassword);
        
        // Validate user entity
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->errorResponse(
                $this->translator->trans('user.validation_failed', ['%errors%' => implode(', ', $errorMessages)]), 
                400
            );
        }

        // Save user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('user.registered_successfully'),
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'username' => $user->getActualUsername(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ]
        ], 201);
    }
}