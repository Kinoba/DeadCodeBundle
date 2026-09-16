<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dead_code');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('redis_dsn')->defaultValue('redis://localhost:6379')->end()
                ->integerNode('sampling_rate')->defaultValue(100)->end()
                ->scalarNode('cache_ttl')->defaultValue(86400)->end()
                ->arrayNode('ignored_paths')
                    ->scalarPrototype()->end()
                    ->defaultValue(['vendor/', 'var/cache/', 'tests/'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
