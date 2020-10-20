<?php

namespace Oro\Bundle\GridFSConfigBundle\DependencyInjection;

use Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory\GridFSAdapterFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OroGridFSConfigExtension extends Extension implements PrependExtensionInterface
{
    private const PARAM_MONGODB_DSN_PREFIX = 'mongodb_gridfs_dsn_';

    /**
     * @{inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * @{inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        // register oro_gridfs gaufrette adapter
        $container->setParameter('oro_gridfs.config_dir', __DIR__.'/../Resources/config');
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('oro_gridfs.yml');

        //process configuration in mongodb_gridfs_dsn_* parameters
        $this->processContainerParameters($container);
    }

    /**
     * @param ContainerBuilder $container
     */
    private function processContainerParameters(ContainerBuilder $container): void
    {
        $parametergrConfigs = $this->getGridfsDsnConfigs($container);
        if (count($parametergrConfigs) === 0) {
            return;
        }

        $gaufretteConfigs = $container->getExtensionConfig('knp_gaufrette');
        $adapterNames = $this->collectConfiguredGaufretteAdaptersNames($gaufretteConfigs);

        $config = [];
        $prefixLength = strlen(self::PARAM_MONGODB_DSN_PREFIX);
        foreach ($parametergrConfigs as $gridfsDsnConfigKey => $dsnString) {
            $adapterName = substr($gridfsDsnConfigKey, $prefixLength);
            if (!in_array($adapterName, $adapterNames)) {
                throw new \RuntimeException(
                    sprintf('Wrong Gaufrette DSN configuration. "%s" adapter cannot be found', $adapterName)
                );
            }
            $config[$adapterName] = [
                'oro_gridfs' => [GridFSAdapterFactory::DSN_STRING_PARAMETER => $dsnString],
            ];
        }

        $gaufretteConfigs[] = ['adapters' => $config];
        $container->setExtensionConfig('knp_gaufrette', $gaufretteConfigs);
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getGridfsDsnConfigs(ContainerBuilder $container): array
    {
        $parametersConfigs = $container->getParameterBag()->all();
        return array_filter(
            $parametersConfigs,
            function ($key) {
                return strpos($key, self::PARAM_MONGODB_DSN_PREFIX) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param array $gaufretteConfigs
     *
     * @return array
     */
    private function collectConfiguredGaufretteAdaptersNames(array $gaufretteConfigs): array
    {
        $adapters = [];
        foreach ($gaufretteConfigs as $config) {
            if (empty($config['adapters'])) {
                continue;
            }
            $adapters[] = array_keys($config['adapters']);
        }

        return array_unique(array_merge(...$adapters));
    }
}
