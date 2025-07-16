<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Provider;

use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbDriverConfig;
use PHPUnit\Framework\TestCase;

class MongoDbDriverConfigTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testCreate(string $dbConfig, string $dbName): void
    {
        $config = new MongoDbDriverConfig($dbConfig);

        $this->assertEquals($dbConfig, $config->getDbConfig());
        $this->assertEquals($dbName, $config->getDbName());
    }

    public function dataProvider(): array
    {
        return [
            'dsn no claster' => [
                'mongodb://user:password@host:27017/attachment',
                'attachment'
            ],
            'dsn with claster' => [
                'mongodb://user:password@host1:27017,host2:27017/cache',
                'cache'
            ],
        ];
    }
}
