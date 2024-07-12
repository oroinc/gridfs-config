<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Provider;

use MongoDB\Driver\Manager;
use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbRequirementsProvider;
use Symfony\Requirements\RequirementCollection;

class MongoDbRequirementsProviderTest extends \PHPUnit\Framework\TestCase
{
    private string $mediaDirectory;

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir();
        $this->mediaDirectory = '/public/media';

        $this->mongoDbDriverManager = new Manager();

        $this->tempDir = sys_get_temp_dir() . $this->mediaDirectory;
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        chmod($this->tempDir, 0777);
        rmdir($this->tempDir);
        rmdir(sys_get_temp_dir() . '/public');
    }

    public function testGetOroRequirementsPositive(): void
    {
        $provider = new MongoDbRequirementsProvider(
            $this->projectDirectory,
            null
        );

        $collection = $provider->getOroRequirements();

        $this->assertInstanceOf(RequirementCollection::class, $collection);

        $requirements = $collection->all();
        $this->assertNotEmpty($requirements);
        $this->assertStringContainsString(
            'public/media directory must be writable',
            $requirements[0]->getTestMessage()
        );
    }

    public function testGetOroRequirementsNegative(): void
    {
        $provider = new MongoDbRequirementsProvider(
            $this->projectDirectory,
            $this->mongoDbDriverManager
        );

        $collection = $provider->getOroRequirements();

        $this->assertNull($collection);
    }

    public function testAddPathWritableRequirementIsFulfilled(): void
    {
        $provider = new MongoDbRequirementsProvider(
            $this->projectDirectory,
            $this->mongoDbDriverManager,
        );

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('addPathWritableRequirement');

        $collection = $method->invoke($provider, $this->mediaDirectory);

        $requirements = $collection->all();
        $this->assertNotEmpty($requirements);
        $this->assertTrue($requirements[0]->isFulfilled());
    }

    public function testAddPathWritableRequirementIsNotFulfilled(): void
    {
        // Change permissions to Read-Only
        chmod($this->tempDir, 0555);

        $provider = new MongoDbRequirementsProvider(
            $this->projectDirectory,
            $this->mongoDbDriverManager,
        );

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('addPathWritableRequirement');

        $collection = $method->invoke($provider, $this->mediaDirectory);

        $requirements = $collection->all();
        $this->assertNotEmpty($requirements);
        $this->assertFalse($requirements[0]->isFulfilled());
    }
}
