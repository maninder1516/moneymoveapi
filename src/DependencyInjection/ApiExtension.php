<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ApiExtension extends Extension
{
    public function getConfiguration(array $config, ContainerBuilder $container): ApiConfiguration
    {
        return new ApiConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new ApiConfiguration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set parameters in the container
        $container->setParameter('api.version.current', $config['version']['current']);
        $container->setParameter('api.version.supported', $config['version']['supported']);
        $container->setParameter('api.version.deprecation_notice', $config['version']['deprecation_notice']);
        
        $container->setParameter('api.rate_limit.requests_per_minute', $config['rate_limit']['requests_per_minute']);
        $container->setParameter('api.rate_limit.burst_limit', $config['rate_limit']['burst_limit']);
        
        $container->setParameter('api.response_format.success_field', $config['response_format']['success_field']);
        $container->setParameter('api.response_format.data_field', $config['response_format']['data_field']);
        $container->setParameter('api.response_format.message_field', $config['response_format']['message_field']);
        $container->setParameter('api.response_format.errors_field', $config['response_format']['errors_field']);
        
        $container->setParameter('api.cors.allow_origins', $config['cors']['allow_origins']);
        $container->setParameter('api.cors.allow_headers', $config['cors']['allow_headers']);
        $container->setParameter('api.cors.allow_methods', $config['cors']['allow_methods']);

        // Load services (if you have any specific to API)
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
    }

    public function getAlias(): string
    {
        return 'api';
    }
}