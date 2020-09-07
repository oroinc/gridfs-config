<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\DependencyInjection\Factory;

use Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory\GridFSAdapterFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GridFSAdapterFactoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var GridFSAdapterFactory */
    private $factory;

    protected function setUp()
    {
        $this->factory = new GridFSAdapterFactory();
    }

    public function testGetKey()
    {
        self::assertEquals('oro_gridfs', $this->factory->getKey());
    }

    public function testAddConfiguration()
    {
        $node = new ArrayNodeDefinition('test');
        $this->factory->addConfiguration($node);

        $childDefinitions = $node->getChildNodeDefinitions();
        self::assertCount(1, $childDefinitions);
        self::assertInstanceOf(ScalarNodeDefinition::class, $childDefinitions['mongodb_gridfs_dsn']);
    }

    public function testCreate()
    {
        $container = new ContainerBuilder();
        $id = 'test_gridfs.adapter';
        $config = ['mongodb_gridfs_dsn' => 'mongodb://user:password@host:27017/attachment'];

        $this->factory->create($container, $id, $config);

        self::assertTrue($container->hasDefinition('oro.mongodb.driver.manager.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('oro.gridfs.bucket.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('test_gridfs.adapter'));

        $bucketConfig = $container->getDefinition('oro.mongodb.driver.manager.test_gridfs.adapter');
        self::assertEquals('mongodb://user:password@host:27017/attachment', $bucketConfig->getArgument(0));

        $bucketConfig = $container->getDefinition('oro.gridfs.bucket.test_gridfs.adapter');
        self::assertEquals('attachment', $bucketConfig->getArgument(1));
    }

    public function testCreateWithClusterConfiguration()
    {
        $container = new ContainerBuilder();
        $id = 'test_gridfs.adapter';
        $config = ['mongodb_gridfs_dsn' => 'mongodb://user:password@host1:27017,host2:27017/cache'];

        $this->factory->create($container, $id, $config);

        self::assertTrue($container->hasDefinition('oro.mongodb.driver.manager.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('oro.gridfs.bucket.test_gridfs.adapter'));
        self::assertTrue($container->hasDefinition('test_gridfs.adapter'));

        $bucketConfig = $container->getDefinition('oro.mongodb.driver.manager.test_gridfs.adapter');
        self::assertEquals('mongodb://user:password@host1:27017,host2:27017/cache', $bucketConfig->getArgument(0));

        $bucketConfig = $container->getDefinition('oro.gridfs.bucket.test_gridfs.adapter');
        self::assertEquals('cache', $bucketConfig->getArgument(1));
    }
}
