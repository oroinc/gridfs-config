<?php

namespace Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory;

use Knp\Bundle\GaufretteBundle\DependencyInjection\Factory\AdapterFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Factory for create GridFs Adapter
 */
class GridFSAdapterFactory implements AdapterFactoryInterface
{
    public const DSN_STRING_PARAMETER = 'mongodb_gridfs_dsn';

    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, $id, array $config)
    {
        $dbConfig = $config[self::DSN_STRING_PARAMETER];
        preg_match('|mongodb:\/\/.*\/(?<db>\w+)$|', $dbConfig, $matches);

        $driverManagerDefinition = new ChildDefinition('oro.mongodb.driver.manager');
        $driverManagerDefinition->addArgument($dbConfig);
        $container->setDefinition('oro.mongodb.driver.manager.' . $id, $driverManagerDefinition);

        $bucketDefinition = new ChildDefinition('oro.gridfs.bucket');
        $bucketDefinition->addArgument($driverManagerDefinition);
        $bucketDefinition->addArgument($matches['db']);
        $container->setDefinition('oro.gridfs.bucket.' . $id, $bucketDefinition);

        $adapterDefinition = new ChildDefinition('oro_gridfs.adapter.gridfs');
        $adapterDefinition->addArgument($bucketDefinition);
        $container->setDefinition($id, $adapterDefinition);
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'oro_gridfs';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeDefinition $node)
    {
        $node
            ->children()
                ->scalarNode(self::DSN_STRING_PARAMETER)->isRequired()->cannotBeEmpty()->end()
            ->end();
    }
}
