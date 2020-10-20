<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\DependencyInjection;

use Knp\Bundle\GaufretteBundle\DependencyInjection\KnpGaufretteExtension;
use Oro\Bundle\GridFSConfigBundle\DependencyInjection\OroGridFSConfigExtension;
use Oro\Component\DependencyInjection\ExtendedContainerBuilder;

class OroGridFSConfigExtensionTest extends \PHPUnit\Framework\TestCase
{
    /** @var OroGridFSConfigExtension */
    protected $extension;

    /** @var ExtendedContainerBuilder */
    protected $container;

    protected function setUp()
    {
        $this->container = new ExtendedContainerBuilder();
        $this->container->registerExtension(new KnpGaufretteExtension());
        $this->container->setExtensionConfig(
            'knp_gaufrette',
            [
                [
                    'adapters' => [
                        'first_adapter'  => 'config1',
                        'second_adapter' => 'config2',
                    ]
                ]
            ]
        );

        $this->extension = new OroGridFSConfigExtension();
    }

    public function testLoad()
    {
        $this->extension->load([], $this->container);

        self::assertTrue($this->container->has('oro_gridfs.adapter.gridfs'));
        self::assertTrue($this->container->has('oro.mongodb.driver.manager'));
        self::assertTrue($this->container->has('oro.gridfs.bucket'));
    }

    public function testPrependWithoutGaufretteConfiguredParameters()
    {
        $this->extension->prepend($this->container);

        self::assertEquals(
            [
                [
                    'adapters' => [
                        'first_adapter'  => 'config1',
                        'second_adapter' => 'config2',
                    ]
                ],
                [
                    'factories' => [
                        '%oro_gridfs.config_dir%/adapter_factories.xml',
                    ]
                ]
            ],
            $this->container->getExtensionConfig('knp_gaufrette')
        );
    }

    public function testPrependOnReconfiguredFirstAdapter()
    {
        $this->container->setParameter('mongodb_gridfs_dsn_first_adapter', 'mongodb://user@host:27017/test');
        $this->extension->prepend($this->container);

        self::assertEquals(
            [
                [
                    'adapters' => ['first_adapter' => 'config1', 'second_adapter' => 'config2'],
                ],
                [
                    'factories' => [
                        '%oro_gridfs.config_dir%/adapter_factories.xml',
                    ]
                ],
                [
                    'adapters' => [
                        'first_adapter' => [
                            'oro_gridfs' => ['mongodb_gridfs_dsn' => 'mongodb://user@host:27017/test']
                        ]
                    ]
                ],
            ],
            $this->container->getExtensionConfig('knp_gaufrette')
        );
    }

    public function testTryToPrependOnNonExistAdapter()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Wrong Gaufrette DSN configuration. "not_existing" adapter cannot be found');

        $this->container->setParameter('mongodb_gridfs_dsn_not_existing', 'mongodb://user@host:27017/test');
        $this->extension->prepend($this->container);
    }
}
