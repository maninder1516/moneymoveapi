<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Smart Banking Logger with Environment-Aware Logging Levels.
 * 
 * Provides intelligent logging that adapts to different environments while
 * maintaining regulatory compliance for financial operations. Automatically
 * filters log levels and content based on environment and configuration.
 * 
 * Features:
 * - Environment-aware log level filtering
 * - Regulatory compliance logging (always active)
 * - Performance impact optimization
 * - Security event prioritization
 * - Configurable detail levels
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class SmartBankingLogger
{
    private readonly bool $isProduction;
    private readonly bool $logDetailedRequests;
    private readonly bool $logPerformanceMetrics;
    private readonly bool $logDebugInfo;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $params
    ) {
        $this->isProduction = $this->params->get('kernel.environment') === 'prod';
        $this->logDetailedRequests = $this->params->get('app.log_detailed_requests') ?? !$this->isProduction;
        $this->logPerformanceMetrics = $this->params->get('app.log_performance_metrics') ?? true;
        $this->logDebugInfo = $this->params->get('app.log_debug_info') ?? !$this->isProduction;
    }

    /**
     * Log financial transaction (ALWAYS logged - regulatory requirement).
     */
    public function logFinancialTransaction(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, [
            'event_type' => 'financial_transaction',
            'compliance' => true,
            'audit_required' => true
        ]));
    }

    /**
     * Log security event (ALWAYS logged - security requirement).
     */
    public function logSecurityEvent(string $message, array $context = []): void
    {
        $this->logger->warning($message, array_merge($context, [
            'event_type' => 'security_event',
            'security_critical' => true,
            'immediate_review' => true
        ]));
    }

    /**
     * Log business operation (environment-aware).
     */
    public function logBusinessOperation(string $message, array $context = []): void
    {
        if ($this->logDetailedRequests || $this->containsCriticalKeywords($message)) {
            $this->logger->info($message, array_merge($context, [
                'event_type' => 'business_operation'
            ]));
        }
    }

    /**
     * Log debug information (only in development).
     */
    public function logDebug(string $message, array $context = []): void
    {
        if ($this->logDebugInfo) {
            $this->logger->debug($message, array_merge($context, [
                'event_type' => 'debug_info',
                'environment' => 'development_only'
            ]));
        }
    }

    /**
     * Log performance metrics (configurable).
     */
    public function logPerformance(string $message, array $context = []): void
    {
        if ($this->logPerformanceMetrics) {
            $this->logger->info($message, array_merge($context, [
                'event_type' => 'performance_metric'
            ]));
        }
    }

    /**
     * Log error (ALWAYS logged).
     */
    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, array_merge($context, [
            'event_type' => 'system_error',
            'requires_investigation' => true
        ]));
    }

    /**
     * Log warning (ALWAYS logged).
     */
    public function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, array_merge($context, [
            'event_type' => 'system_warning'
        ]));
    }

    /**
     * Check if message contains critical keywords that should always be logged.
     */
    private function containsCriticalKeywords(string $message): bool
    {
        $criticalKeywords = [
            'transfer', 'transaction', 'balance', 'account', 'payment',
            'withdrawal', 'deposit', 'currency', 'conversion', 'authentication',
            'authorization', 'security', 'fraud', 'failed', 'error', 'exception'
        ];

        $messageLower = strtolower($message);
        
        foreach ($criticalKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get logging configuration status.
     */
    public function getLoggingConfig(): array
    {
        return [
            'environment' => $this->isProduction ? 'production' : 'development',
            'detailed_requests' => $this->logDetailedRequests,
            'performance_metrics' => $this->logPerformanceMetrics,
            'debug_info' => $this->logDebugInfo,
            'financial_transactions' => true, // Always enabled
            'security_events' => true // Always enabled
        ];
    }
}