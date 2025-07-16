<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\DependencyInjection;

use Knp\Bundle\GaufretteBundle\DependencyInjection\KnpGaufretteExtension;
use Oro\Bundle\GridFSConfigBundle\DependencyInjection\OroGridFSConfigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OroGridFSConfigExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();

        $extension = new OroGridFSConfigExtension();
        $extension->load([], $container);

        self::assertNotEmpty($container->getDefinitions());
    }

    public function testPrepend(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new KnpGaufretteExtension());

        $extension = new OroGridFSConfigExtension();
        $extension->prepend($container);

        self::assertEquals(
            dirname((new \ReflectionClass(OroGridFSConfigExtension::class))->getFileName())
            . '/../Resources/config',
            $container->getParameter('oro_gridfs.config_dir')
        );
        self::assertEquals(
            [
                ['factories' => ['%oro_gridfs.config_dir%/adapter_factories.xml']]
            ],
            $container->getExtensionConfig('knp_gaufrette')
        );
    }
}
