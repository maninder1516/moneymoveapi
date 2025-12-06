<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    (new Dotenv())->bootEnv(__DIR__ . '/.env');
}

// Initialize Symfony kernel
$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));

echo "=== MoneyMove API Transfer Debug ===\n";
echo "Environment: " . ($_ENV['APP_ENV'] ?? 'dev') . "\n";
echo "Debug Mode: " . (($_ENV['APP_DEBUG'] ?? true) ? 'enabled' : 'disabled') . "\n\n";

try {
    // Boot the kernel
    $kernel->boot();
    
    // Get container
    $container = $kernel->getContainer();
    
    echo "✅ Kernel booted successfully\n";
    
    // Test database connection
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $connection = $entityManager->getConnection();
    $connection->executeQuery('SELECT 1')->fetchAssociative();
    
    echo "✅ Database connection working\n";
    
    // Test account service
    $accountService = $container->get('App\Service\Api\V1\AccountService');
    echo "✅ AccountService loaded\n";
    
    // Test encryption service
    $encryptionService = $container->get('App\Service\AccountEncryptionService');
    echo "✅ AccountEncryptionService loaded\n";
    
    // Test transformer service
    $transformerService = $container->get('App\Service\Api\V1\AccountDtoTransformerService');
    echo "✅ AccountDtoTransformerService loaded\n";
    
    // Get user repository and find user with ID 6 (from our fixtures)
    $userRepository = $entityManager->getRepository('App\Entity\User');
    $user = $userRepository->find(6);
    
    if (!$user) {
        echo "❌ User with ID 6 not found\n";
        exit(1);
    }
    
    echo "✅ User found: " . $user->getName() . " (" . $user->getEmail() . ")\n";
    
    // Test getting user account
    try {
        $fromAccount = $accountService->getUserAccount($user, 1);
        echo "✅ Source account found: ID=" . $fromAccount->getId() . ", Currency=" . $fromAccount->getCurrency() . ", Balance=" . $fromAccount->getBalance() . "\n";
    } catch (\Exception $e) {
        echo "❌ Error getting source account: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Test getting destination account by number
    try {
        $toAccount = $accountService->getAccountByNumber('ACC00010007498344');
        echo "✅ Destination account found: ID=" . $toAccount->getId() . ", Currency=" . $toAccount->getCurrency() . ", Balance=" . $toAccount->getBalance() . "\n";
    } catch (\Exception $e) {
        echo "❌ Error getting destination account: " . $e->getMessage() . "\n";
        echo "Trying to debug account lookup...\n";
        
        // Debug account lookup
        $accountRepo = $entityManager->getRepository('App\Entity\Account');
        $hash = hash('sha256', 'ACC00010007498344');
        echo "Looking for hash: " . $hash . "\n";
        
        $account = $accountRepo->findOneBy(['accountNumberHash' => $hash]);
        if ($account) {
            echo "Account found by hash in repository\n";
        } else {
            echo "Account NOT found by hash in repository\n";
        }
        
        exit(1);
    }
    
    echo "\n🎉 All services are working correctly!\n";
    echo "The issue might be in the JWT authentication or request handling.\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}