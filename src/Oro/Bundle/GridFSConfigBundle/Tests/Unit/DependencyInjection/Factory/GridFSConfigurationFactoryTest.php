<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\DependencyInjection\Factory;

use Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory\GridFSConfigurationFactory;

class GridFSConfigurationFactoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var GridFSConfigurationFactory */
    private $factory;

    protected function setUp(): void
    {
        $this->factory = new GridFSConfigurationFactory();
    }

    public function testGetAdapterConfiguration()
    {
        $configString = 'mongodb:127.0.0.1/test';
        self::assertEquals(
            [
                'oro_gridfs' => [
                    'mongodb_gridfs_dsn' => $configString
                ]
            ],
            $this->factory->getAdapterConfiguration($configString)
        );
    }

    public function testGetKey()
    {
        self::assertEquals('gridfs', $this->factory->getKey());
    }

    public function testGetHint()
    {
        self::assertEquals(
            'The configuration string is "gridfs:{MongoDB connection string}",'
            . ' for example "gridfs:mongodb://127.0.0.1:27017/media".'
            . ' For more detail see https://doc.oroinc.com/backend/bundles/platform/GridFSConfigBundle/'
            . '#adapters-configuration-with-parameters-yml',
            $this->factory->getHint()
        );
    }
}
