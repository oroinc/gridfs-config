<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\DependencyInjection\Factory;

use MongoDB\Driver\Manager;
use Oro\Bundle\GridFSConfigBundle\Adapter\GridFS;
use Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory\GridFSAdapterFactory;
use Oro\Bundle\GridFSConfigBundle\GridFS\Bucket;
use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbDriverConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class GridFSAdapterFactoryTest extends TestCase
{
    private GridFSAdapterFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new GridFSAdapterFactory();
    }

    public function testGetKey(): void
    {
        self::assertEquals('oro_gridfs', $this->factory->getKey());
    }

    public function testAddConfiguration(): void
    {
        $node = new ArrayNodeDefinition('test');
        $this->factory->addConfiguration($node);

        $childDefinitions = $node->getChildNodeDefinitions();
        self::assertCount(1, $childDefinitions);
        self::assertInstanceOf(ScalarNodeDefinition::class, $childDefinitions['mongodb_gridfs_dsn']);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $baseDriverConfig = new Definition(MongoDbDriverConfig::class);
        $baseDriverConfig->setAbstract(true);
        $container->setDefinition('oro.mongodb.driver.config', $baseDriverConfig);

        $baseDriverManager = new Definition(Manager::class);
        $baseDriverManager->setAbstract(true);
        $container->setDefinition('oro.mongodb.driver.manager', $baseDriverManager);

        $baseGridFsBucket = new Definition(Bucket::class);
        $baseGridFsBucket->setAbstract(true);
        $container->setDefinition('oro.gridfs.bucket', $baseGridFsBucket);

        $baseGridFsAdapter = new Definition(GridFS::class);
        $baseGridFsAdapter->setAbstract(true);
        $container->setDefinition('oro_gridfs.adapter.gridfs', $baseGridFsAdapter);

        return $container;
    }

    public function testCreate(): void
    {
        $container = $this->createContainer();
        $id = 'test_gridfs.adapter';
        $config = ['mongodb_gridfs_dsn' => 'mongodb://user:password@host:27017/attachment'];

        $this->factory->create($container, $id, $config);

        self::assertTrue($container->hasDefinition('oro.mongodb.driver.manager.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('oro.gridfs.bucket.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('test_gridfs.adapter'));

        $bucketConfig = $container->getDefinition('oro.mongodb.driver.manager.test_gridfs.adapter');
        self::assertEquals(
            "service('oro.mongodb.driver.config.test_gridfs.adapter').getDbConfig()",
            (string) $bucketConfig->getArgument(0)
        );

        $bucketConfig = $container->getDefinition('oro.gridfs.bucket.test_gridfs.adapter');
        self::assertEquals(
            "service('oro.mongodb.driver.config.test_gridfs.adapter').getDbName()",
            (string) $bucketConfig->getArgument(1)
        );
    }

    public function testCreateWithClusterConfiguration(): void
    {
        $container = $this->createContainer();
        $id = 'test_gridfs.adapter';
        $config = ['mongodb_gridfs_dsn' => 'mongodb://user:password@host1:27017,host2:27017/cache'];

        $this->factory->create($container, $id, $config);

        self::assertTrue($container->hasDefinition('oro.mongodb.driver.manager.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('oro.gridfs.bucket.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('test_gridfs.adapter'));

        $bucketConfig = $container->getDefinition('oro.mongodb.driver.manager.test_gridfs.adapter');
        self::assertEquals(
            "service('oro.mongodb.driver.config.test_gridfs.adapter').getDbConfig()",
            (string) $bucketConfig->getArgument(0)
        );

        $bucketConfig = $container->getDefinition('oro.gridfs.bucket.test_gridfs.adapter');
        self::assertEquals(
            "service('oro.mongodb.driver.config.test_gridfs.adapter').getDbName()",
            (string) $bucketConfig->getArgument(1)
        );
    }
}
