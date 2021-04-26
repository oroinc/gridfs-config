<?php

namespace Oro\Bundle\GridFSConfigBundle\DependencyInjection\Factory;

use Oro\Bundle\GaufretteBundle\DependencyInjection\Factory\ConfigurationFactoryInterface;

/**
 * A factory to configure Gaufrette adapters for MongoDB GridFS storage.
 */
class GridFSConfigurationFactory implements ConfigurationFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAdapterConfiguration(string $configString): array
    {
        return [
            'oro_gridfs' => [
                'mongodb_gridfs_dsn' => $configString
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return 'gridfs';
    }

    /**
     * {@inheritdoc}
     */
    public function getHint(): string
    {
        return
            'The configuration string is "gridfs:{MongoDB connection string}",'
            . ' for example "gridfs:mongodb://127.0.0.1:27017/media".'
            . ' For more detail see https://doc.oroinc.com/backend/architecture/tech-stack/file-storage'
            . '#file-system-adapters-configuration-with-parameters-yml';
    }
}
