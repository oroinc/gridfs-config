<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\GridFSConfigBundle\DependencyInjection\OroGridFSConfigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OroGridFSConfigExtensionTest extends \PHPUnit\Framework\TestCase
{
    /** @var ContainerBuilder */
    private $container;

    /** @var OroGridFSConfigExtension */
    private $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new OroGridFSConfigExtension();
    }

    public function testLoad()
    {
        $this->extension->load([], $this->container);

        self::assertTrue($this->container->has('oro_gridfs.adapter.gridfs'));
        self::assertTrue($this->container->has('oro.mongodb.driver.manager'));
        self::assertTrue($this->container->has('oro.gridfs.bucket'));
    }
}
