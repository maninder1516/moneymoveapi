<?php

declare(strict_types=1);

namespace App\Service\Api;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class ApiConfigService
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
    }

    public function getCurrentVersion(): string
    {
        return $this->parameterBag->get('api.version.current');
    }

    public function getSupportedVersions(): array
    {
        return $this->parameterBag->get('api.version.supported');
    }

    public function getDeprecationNotice(): string
    {
        return $this->parameterBag->get('api.version.deprecation_notice');
    }

    public function getRateLimitRequestsPerMinute(): int
    {
        return $this->parameterBag->get('api.rate_limit.requests_per_minute');
    }

    public function getRateLimitBurstLimit(): int
    {
        return $this->parameterBag->get('api.rate_limit.burst_limit');
    }

    public function getResponseFormat(): array
    {
        return [
            'success_field' => $this->parameterBag->get('api.response_format.success_field'),
            'data_field' => $this->parameterBag->get('api.response_format.data_field'),
            'message_field' => $this->parameterBag->get('api.response_format.message_field'),
            'errors_field' => $this->parameterBag->get('api.response_format.errors_field'),
        ];
    }

    public function getCorsSettings(): array
    {
        return [
            'allow_origins' => $this->parameterBag->get('api.cors.allow_origins'),
            'allow_headers' => $this->parameterBag->get('api.cors.allow_headers'),
            'allow_methods' => $this->parameterBag->get('api.cors.allow_methods'),
        ];
    }

    public function isVersionSupported(string $version): bool
    {
        return in_array($version, $this->getSupportedVersions(), true);
    }

    public function isCurrentVersion(string $version): bool
    {
        return $version === $this->getCurrentVersion();
    }
}