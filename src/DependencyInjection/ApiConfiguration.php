<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ApiConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('api');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('version')
                    ->children()
                        ->scalarNode('current')->defaultValue('v1')->end()
                        ->arrayNode('supported')
                            ->scalarPrototype()->end()
                            ->defaultValue(['v1', 'v2'])
                        ->end()
                        ->scalarNode('deprecation_notice')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limit')
                    ->children()
                        ->integerNode('requests_per_minute')->defaultValue(60)->end()
                        ->integerNode('burst_limit')->defaultValue(100)->end()
                    ->end()
                ->end()
                ->arrayNode('response_format')
                    ->children()
                        ->scalarNode('success_field')->defaultValue('success')->end()
                        ->scalarNode('data_field')->defaultValue('data')->end()
                        ->scalarNode('message_field')->defaultValue('message')->end()
                        ->scalarNode('errors_field')->defaultValue('errors')->end()
                    ->end()
                ->end()
                ->arrayNode('cors')
                    ->children()
                        ->arrayNode('allow_origins')
                            ->scalarPrototype()->end()
                            ->defaultValue(['*'])
                        ->end()
                        ->arrayNode('allow_headers')
                            ->scalarPrototype()->end()
                            ->defaultValue(['Content-Type', 'Authorization', 'X-Requested-With'])
                        ->end()
                        ->arrayNode('allow_methods')
                            ->scalarPrototype()->end()
                            ->defaultValue(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}