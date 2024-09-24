<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Provider;

use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbDriverConfig;
use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbRequirementsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Requirements\RequirementCollection;

class MongoDbRequirementsProviderTest extends \PHPUnit\Framework\TestCase
{
    private string $mediaDirectory;
    private MongoDbRequirementsProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $projectDirectory = sys_get_temp_dir();
        $this->mediaDirectory = '/public/media';

        $this->mongoDbDriverConfig = $this->createMock(MongoDbDriverConfig::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->provider = new MongoDbRequirementsProvider(
            $projectDirectory,
            $this->mongoDbDriverConfig,
            $logger
        );

        $this->tempDir = sys_get_temp_dir() . $this->mediaDirectory;
        mkdir($this->tempDir, 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        chmod($this->tempDir, 0777);
        rmdir($this->tempDir);
        rmdir(sys_get_temp_dir() . '/public');
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
