<?php

namespace Oro\Bundle\GridFSConfigBundle;

use Oro\Bundle\GaufretteBundle\DependencyInjection\OroGaufretteExtension;
use Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory\GridFSConfigurationFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OroGridFSConfigBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var OroGaufretteExtension $gaufretteExtension */
        $gaufretteExtension = $container->getExtension('oro_gaufrette');
        $gaufretteExtension->addConfigurationFactory(new GridFSConfigurationFactory());
    }
}
