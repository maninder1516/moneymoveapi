<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CacheService;
use App\Service\Api\V1\CurrencyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for managing cache operations.
 * 
 * Provides cache management functionality including:
 * - Cache status and statistics
 * - Cache invalidation for currency rates
 * - Clear all cache or specific patterns
 * - Test cache functionality
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
#[AsCommand(
    name: 'app:cache:manage',
    description: 'Manage application cache (Redis + Symfony Cache fallback)'
)]
class CacheManageCommand extends Command
{
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CurrencyService $currencyService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: status, clear, test, currency-clear')
            ->addOption('pattern', 'p', InputOption::VALUE_OPTIONAL, 'Cache key pattern for clear operation', '*')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'From currency for currency-clear operation')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'To currency for currency-clear operation')
            ->setHelp('
This command allows you to manage the application cache system.

Available actions:
  status         - Show cache status and statistics
  clear          - Clear cache entries (use --pattern to specify pattern)
  test           - Test cache functionality 
  currency-clear - Clear currency rate cache (use --from and --to for specific pair)

Examples:
  php bin/console app:cache:manage status
  php bin/console app:cache:manage clear --pattern="currency_rate:*"
  php bin/console app:cache:manage currency-clear --from=USD --to=EUR
  php bin/console app:cache:manage test
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        return match ($action) {
            'status' => $this->showCacheStatus($io),
            'clear' => $this->clearCache($io, $input->getOption('pattern')),
            'test' => $this->testCache($io),
            'currency-clear' => $this->clearCurrencyCache($io, $input->getOption('from'), $input->getOption('to')),
            default => $this->showError($io, "Unknown action: {$action}")
        };
    }

    /**
     * Show cache status and statistics.
     */
    private function showCacheStatus(SymfonyStyle $io): int
    {
        $io->title('Cache System Status');

        $stats = $this->cacheService->getStats();

        // Redis Status
        $io->section('Redis Status');
        if ($stats['redis_available']) {
            $io->success('Redis is available and connected');
            
            if (isset($stats['redis_info'])) {
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Connected Clients', $stats['redis_info']['connected_clients']],
                        ['Memory Used', $stats['redis_info']['used_memory_human']],
                        ['Cache Hits', $stats['redis_info']['keyspace_hits']],
                        ['Cache Misses', $stats['redis_info']['keyspace_misses']],
                    ]
                );
            }
        } else {
            $io->error('Redis is not available');
            if (isset($stats['redis_error'])) {
                $io->text('Error: ' . $stats['redis_error']);
            }
        }

        // Fallback Cache Status
        $io->section('Fallback Cache');
        $io->text('Type: ' . $stats['fallback_type']);
        $io->info('Fallback cache is always available (Symfony filesystem cache)');

        return Command::SUCCESS;
    }

    /**
     * Clear cache entries.
     */
    private function clearCache(SymfonyStyle $io, string $pattern): int
    {
        $io->title('Clearing Cache');
        $io->text("Pattern: {$pattern}");

        if ($io->confirm('Are you sure you want to clear cache entries?', false)) {
            $result = $this->cacheService->clear($pattern);
            
            if ($result) {
                $io->success("Cache cleared successfully for pattern: {$pattern}");
            } else {
                $io->warning("Cache clear completed (some entries may not have been cleared)");
            }
        } else {
            $io->text('Operation cancelled');
        }

        return Command::SUCCESS;
    }

    /**
     * Test cache functionality.
     */
    private function testCache(SymfonyStyle $io): int
    {
        $io->title('Testing Cache Functionality');

        // Test basic cache operations
        $testKey = 'test:cache:' . time();
        $testValue = ['message' => 'Hello Cache!', 'timestamp' => time()];

        $io->section('Basic Cache Operations');
        
        // Test SET
        $io->text('Testing cache SET...');
        $setResult = $this->cacheService->set($testKey, $testValue, 60);
        $io->text($setResult ? '✓ SET successful' : '✗ SET failed');

        // Test GET
        $io->text('Testing cache GET...');
        $getValue = $this->cacheService->get($testKey);
        $getResult = $getValue !== null && $getValue['message'] === $testValue['message'];
        $io->text($getResult ? '✓ GET successful' : '✗ GET failed');
        
        if ($getResult) {
            $io->text('Retrieved value: ' . json_encode($getValue));
        }

        // Test DELETE
        $io->text('Testing cache DELETE...');
        $deleteResult = $this->cacheService->delete($testKey);
        $io->text($deleteResult ? '✓ DELETE successful' : '✗ DELETE failed');

        // Test currency conversion caching
        $io->section('Currency Conversion Cache Test');
        
        try {
            $io->text('Testing currency conversion with caching...');
            
            // First call (should cache the rate)
            $start = microtime(true);
            $result1 = $this->currencyService->convertAmount('100.00', 'USD', 'EUR');
            $time1 = (microtime(true) - $start) * 1000;
            
            // Second call (should use cache)
            $start = microtime(true);
            $result2 = $this->currencyService->convertAmount('100.00', 'USD', 'EUR');
            $time2 = (microtime(true) - $start) * 1000;
            
            $io->table(
                ['Call', 'Result', 'Time (ms)', 'Rate'],
                [
                    ['1st (DB)', $result1['converted_amount'], sprintf('%.2f', $time1), $result1['exchange_rate']],
                    ['2nd (Cache)', $result2['converted_amount'], sprintf('%.2f', $time2), $result2['exchange_rate']],
                ]
            );
            
            if ($time2 < $time1) {
                $io->success('Cache is working! Second call was faster.');
            } else {
                $io->warning('Cache may not be working as expected.');
            }
            
        } catch (\Exception $e) {
            $io->error('Currency conversion test failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }

    /**
     * Clear currency rate cache.
     */
    private function clearCurrencyCache(SymfonyStyle $io, ?string $from, ?string $to): int
    {
        $io->title('Clearing Currency Rate Cache');

        if ($from && $to) {
            $io->text("Clearing cache for currency pair: {$from} -> {$to}");
        } else {
            $io->text('Clearing all currency rate cache');
        }

        if ($io->confirm('Are you sure you want to clear currency rate cache?', false)) {
            $result = $this->currencyService->clearRateCache($from, $to);
            
            if ($result) {
                $io->success('Currency rate cache cleared successfully');
            } else {
                $io->warning('Currency rate cache clear completed (some entries may not have been cleared)');
            }
        } else {
            $io->text('Operation cancelled');
        }

        return Command::SUCCESS;
    }

    /**
     * Show error message.
     */
    private function showError(SymfonyStyle $io, string $message): int
    {
        $io->error($message);
        $io->text('Use --help for available actions');
        return Command::FAILURE;
    }
}