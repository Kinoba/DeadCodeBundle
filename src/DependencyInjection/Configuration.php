<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        /** @var TreeBuilder<'array'> $treeBuilder */
        $treeBuilder = new TreeBuilder('dead_code', 'array');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->scalarNode('redis_dsn')
            ->defaultValue('redis://localhost:6379')
            ->end()
            ->integerNode('sampling_rate')
            ->defaultValue(100)
            ->end()
            ->scalarNode('cache_ttl')
            ->defaultValue(86_400)
            ->end()
            ->arrayNode('ignored_paths')
            ->scalarPrototype()
            ->end()
            ->defaultValue(['vendor/', 'var/cache/', 'tests/'])
            ->end()
            ->end();

        return $treeBuilder;
    }
}
