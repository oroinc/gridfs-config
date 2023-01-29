<?php

namespace Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory;

use Knp\Bundle\GaufretteBundle\DependencyInjection\Factory\AdapterFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A factory to create Gaufrette adapter for MongoDB GridFS storage.
 */
class GridFSAdapterFactory implements AdapterFactoryInterface
{
    private const DSN_STRING_PARAMETER = 'mongodb_gridfs_dsn';

    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, $id, array $config): void
    {
        $dbConfig = $config[self::DSN_STRING_PARAMETER];
        preg_match('|mongodb:\/\/.*\/(?<db>\w+)$|', $dbConfig, $matches);

        $driverManagerId = 'oro.mongodb.driver.manager.' . $id;
        $driverManagerDefinition = new ChildDefinition('oro.mongodb.driver.manager');
        $driverManagerDefinition->addArgument($dbConfig);
        $container->setDefinition($driverManagerId, $driverManagerDefinition);

        $bucketId = 'oro.gridfs.bucket.' . $id;
        $bucketDefinition = new ChildDefinition('oro.gridfs.bucket');
        $bucketDefinition->addArgument(new Reference($driverManagerId));
        $bucketDefinition->addArgument($matches['db']);
        $container->setDefinition($bucketId, $bucketDefinition);

        $adapterDefinition = new ChildDefinition('oro_gridfs.adapter.gridfs');
        $adapterDefinition->addArgument(new Reference($bucketId));
        $container->setDefinition($id, $adapterDefinition);
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return 'oro_gridfs';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->children()
            ->scalarNode(self::DSN_STRING_PARAMETER)->isRequired()->cannotBeEmpty()->end()
            ->end();
    }
}
