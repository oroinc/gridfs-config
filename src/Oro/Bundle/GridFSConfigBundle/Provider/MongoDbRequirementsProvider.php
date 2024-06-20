<?php

declare(strict_types=1);

namespace Oro\Bundle\GridFSConfigBundle\Provider;

use Oro\Bundle\InstallerBundle\Provider\AbstractRequirementsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Requirements\RequirementCollection;

/**
 * MongoDB requirements provider
 */
class MongoDbRequirementsProvider extends AbstractRequirementsProvider
{
    public function __construct(
        protected string $projectDirectory,
        private ?MongoDbDriverConfig $mongoDbDriverConfig,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function getOroRequirements(): ?RequirementCollection
    {
        if (!$this->mongoDbDriverConfig) {
            return $this->addPathWritableRequirement('public/media');
        }

        if (!$this->mongoDbDriverConfig->isConnected($this->logger)) {
            return $this->addPathWritableRequirement('public/media');
        }

        return null;
    }

    protected function addPathWritableRequirement(string $path): RequirementCollection
    {
        $collection = new RequirementCollection();

        $fullPath = $this->projectDirectory . '/' . $path;
        $pathType = is_file($fullPath) ? 'file' : 'directory';

        $collection->addRequirement(
            is_writable($fullPath),
            $path . ' directory must be writable',
            'Change the permissions of the "<strong>' . $path . '</strong>" ' . $pathType . ' so' .
            ' that the web server can write into it.'
        );

        return $collection;
    }
}
