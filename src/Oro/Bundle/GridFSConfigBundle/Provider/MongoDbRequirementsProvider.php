<?php

declare(strict_types=1);

namespace Oro\Bundle\GridFSConfigBundle\Provider;

use MongoDB\Driver\Manager;
use Oro\Bundle\InstallerBundle\Provider\AbstractRequirementsProvider;
use Symfony\Requirements\RequirementCollection;

/**
 * MongoDB requirements provider
 */
class MongoDbRequirementsProvider extends AbstractRequirementsProvider
{
    public function __construct(
        protected string $projectDirectory,
        private ?Manager $mongoDbDriverManager,
    ) {
    }

    /**
     * @inhericDoc
     */
    public function getOroRequirements(): ?RequirementCollection
    {
        if (!$this->mongoDbDriverManager) {
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
