<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\Extension;

class DeadCodeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        $container->setParameter($this->getAlias() . '.enabled', $config['enabled']);
        $container->setParameter($this->getAlias() . '.redis_dsn', $config['redis_dsn']);
        $container->setParameter($this->getAlias() . '.sampling_rate', $config['sampling_rate']);
        $container->setParameter($this->getAlias() . '.cache_ttl', $config['cache_ttl']);
        $container->setParameter($this->getAlias() . '.ignored_paths', $config['ignored_paths']);
    }
}
