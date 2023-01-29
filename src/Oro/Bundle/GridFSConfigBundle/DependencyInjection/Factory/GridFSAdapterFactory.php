<?php

namespace Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory;

use Knp\Bundle\GaufretteBundle\DependencyInjection\Factory\AdapterFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

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

        $parametersProviderId = 'oro.mongodb.driver.config.' . $id;
        $parametersProvider = new ChildDefinition('oro.mongodb.driver.config');
        $parametersProvider->addArgument($dbConfig);
        $container->setDefinition($parametersProviderId, $parametersProvider);

        $driverManagerId = 'oro.mongodb.driver.manager.' . $id;
        $driverManagerDefinition = new ChildDefinition('oro.mongodb.driver.manager');
        $driverManagerDefinition->addArgument(new Expression(
            sprintf("service('%s').getDbConfig()", $parametersProviderId)
        ));
        $container->setDefinition($driverManagerId, $driverManagerDefinition);

        $bucketId = 'oro.gridfs.bucket.' . $id;
        $bucketDefinition = new ChildDefinition('oro.gridfs.bucket');
        $bucketDefinition->addArgument(new Reference($driverManagerId));
        $bucketDefinition->addArgument(new Expression(
            sprintf("service('%s').getDbName()", $parametersProviderId)
        ));
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
