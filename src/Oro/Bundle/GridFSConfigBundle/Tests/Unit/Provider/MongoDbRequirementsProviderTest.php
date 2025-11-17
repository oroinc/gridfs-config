<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Provider;

use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbDriverConfig;
use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbRequirementsProvider;
use Oro\Component\Testing\TempDirExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Requirements\RequirementCollection;

class MongoDbRequirementsProviderTest extends TestCase
{
    use TempDirExtension;

    private string $mediaDirectory;
    private string $tempDir;
    private MongoDbDriverConfig|MockObject $mongoDbDriverConfig;
    private MongoDbRequirementsProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $projectDirectory = $this->getTempDir('mongo_db_requirements_provider');
        $this->mediaDirectory = '/public/media';

        $this->mongoDbDriverConfig = $this->createMock(MongoDbDriverConfig::class);

        $this->provider = new MongoDbRequirementsProvider(
            $projectDirectory,
            $this->mongoDbDriverConfig,
            $this->createMock(LoggerInterface::class)
        );

        $this->tempDir = $projectDirectory . $this->mediaDirectory;
        mkdir($this->tempDir, 0777, true);
    }

    public function testGetOroRequirementsPositive(): void
    {
        $this->mongoDbDriverConfig->expects($this->once())
            ->method('isConnected')
            ->willReturn(false);

        $collection = $this->provider->getOroRequirements();

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
        $this->mongoDbDriverConfig->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $collection = $this->provider->getOroRequirements();

        $this->assertNull($collection);
    }

    public function testAddPathWritableRequirementIsFulfilled(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('addPathWritableRequirement');

        $collection = $method->invoke($this->provider, $this->mediaDirectory);

        $requirements = $collection->all();
        $this->assertNotEmpty($requirements);
        $this->assertTrue($requirements[0]->isFulfilled());
    }

    public function testAddPathWritableRequirementIsNotFulfilled(): void
    {
        // Change permissions to Read-Only
        chmod($this->tempDir, 0555);

        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('addPathWritableRequirement');

        $collection = $method->invoke($this->provider, $this->mediaDirectory);

        $requirements = $collection->all();
        $this->assertNotEmpty($requirements);
        $this->assertFalse($requirements[0]->isFulfilled());
    }
}
