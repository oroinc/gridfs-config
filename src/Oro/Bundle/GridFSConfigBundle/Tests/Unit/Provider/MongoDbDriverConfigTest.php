<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Provider;

use MongoDB\Driver\Manager;
use MongoDB\Exception\InvalidArgumentException;
use Oro\Bundle\GridFSConfigBundle\Provider\MongoDbDriverConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MongoDbDriverConfigTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testCreate(string $dbConfig, string $dbName): void
    {
        $config = new MongoDbDriverConfig($dbConfig);

        self::assertSame($dbConfig, $config->getDbConfig());
        self::assertSame($dbName, $config->getDbName());
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
            'plain single host' => [
                'mongodb://127.0.0.1:27017/media',
                'media'
            ],
            'credentials and options' => [
                'mongodb://user:pass@h1:27017/media?replicaSet=rs0&authSource=admin',
                'media'
            ],
            'several hosts and options' => [
                'mongodb://user:pass@h1:27017,h2:27017,h3:27017/media?replicaSet=rs0',
                'media'
            ],
            'tls options' => [
                'mongodb://user:pass@h1:27017/media?tls=true&tlsCAFile=/pki/ca.crt',
                'media'
            ],
            'seedlist' => [
                'mongodb+srv://user:pass@cluster0.example.com/media',
                'media'
            ],
            'seedlist with options' => [
                'mongodb+srv://user:pass@cluster0.example.com/media?authSource=admin',
                'media'
            ],
            'ipv6 host' => [
                'mongodb://[::1]:27017/media',
                'media'
            ],
            'unix domain socket' => [
                'mongodb://%2Ftmp%2Fmongodb-27017.sock/media?directConnection=true',
                'media'
            ],
            'percent encoded credentials' => [
                'mongodb://user:p%40ss%2Fword@h1:27017/media',
                'media'
            ],
            'database name with a dash' => [
                'mongodb://host:27017/media-files',
                'media-files'
            ],
            'percent encoded number sign in the database name' => [
                'mongodb://host:27017/media%23files',
                'media#files'
            ],
            'number sign in the database name' => [
                'mongodb://host:27017/media#files',
                'media#files'
            ],
            'number sign in the password' => [
                'mongodb://user:pa#ss@host:27017/media',
                'media'
            ],
            'question mark in the password' => [
                'mongodb://user:pa?ss@host:27017/media',
                'media'
            ],
            'at sign in the database name' => [
                'mongodb://user@host:27017/med@ia',
                'med@ia'
            ],
        ];
    }

    /**
     * The driver must resolve the same connection strings this class accepts, otherwise the failure
     * moves from here to the first file operation. The driver parses without any input or output,
     * except for a seed list, which it resolves through DNS.
     *
     * @dataProvider dataProvider
     */
    public function testTheDriverAcceptsTheSameConnectionString(string $dbConfig): void
    {
        if (str_starts_with($dbConfig, 'mongodb+srv://')) {
            self::markTestSkipped('A seed list needs a name lookup.');
        }

        $config = new MongoDbDriverConfig($dbConfig);

        new Manager($config->getDbConfig());
        self::assertSame($dbConfig, $config->getDbConfig());
    }

    /**
     * A value that comes from a file secret ends with a line break.
     */
    public function testSurroundingWhitespaceIsIgnored(): void
    {
        $config = new MongoDbDriverConfig("  mongodb://127.0.0.1:27017/media\n");

        self::assertSame('mongodb://127.0.0.1:27017/media', $config->getDbConfig());
        self::assertSame('media', $config->getDbName());
    }

    /**
     * @dataProvider invalidConnectionStringDataProvider
     */
    public function testInvalidConnectionStringIsRejected(string $dbConfig, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new MongoDbDriverConfig($dbConfig);
    }

    public function invalidConnectionStringDataProvider(): array
    {
        $noDatabase = 'The MongoDB connection string must contain a database name';
        $badScheme = 'The MongoDB connection string must use the "mongodb" or "mongodb+srv" scheme';
        $badName = 'The MongoDB database name must not contain a control character, a space';

        return [
            'no database' => [
                'mongodb://127.0.0.1:27017',
                $noDatabase
            ],
            'trailing slash only' => [
                'mongodb://127.0.0.1:27017/',
                $noDatabase
            ],
            'options but no database' => [
                'mongodb://127.0.0.1:27017/?replicaSet=rs0',
                $noDatabase
            ],
            'options before the database name' => [
                'mongodb://127.0.0.1:27017?replicaSet=rs0/media',
                $noDatabase
            ],
            'scheme only' => [
                'mongodb://',
                $noDatabase
            ],
            'seedlist scheme only' => [
                'mongodb+srv://',
                $noDatabase
            ],
            'foreign scheme' => [
                'redis://127.0.0.1:6379/1',
                $badScheme
            ],
            'no scheme' => [
                '127.0.0.1:27017/media',
                $badScheme
            ],
            'upper case scheme' => [
                'MONGODB://127.0.0.1:27017/media',
                $badScheme
            ],
            'missing slashes after the scheme' => [
                'mongodb:user:password@127.0.0.1:27017/media',
                $badScheme
            ],
            'space in the database name' => [
                'mongodb://127.0.0.1:27017/media files',
                $badName
            ],
            'percent encoded space in the database name' => [
                'mongodb://127.0.0.1:27017/media%20files',
                $badName
            ],
            'dot in the database name' => [
                'mongodb://127.0.0.1:27017/media.files',
                $badName
            ],
            'percent encoded dot in the database name' => [
                'mongodb://127.0.0.1:27017/media%2Efiles',
                $badName
            ],
            'nested path' => [
                'mongodb://127.0.0.1:27017/media/files',
                $badName
            ],
            'percent encoded slash in the database name' => [
                'mongodb://127.0.0.1:27017/media%2Ffiles',
                $badName
            ],
            'double slash before the database name' => [
                'mongodb://127.0.0.1:27017//media',
                $badName
            ],
            'trailing slash after the database name' => [
                'mongodb://127.0.0.1:27017/media/',
                $badName
            ],
            'percent encoded line break in the database name' => [
                'mongodb://127.0.0.1:27017/media%0Afiles',
                $badName
            ],
            'percent encoded null byte in the database name' => [
                'mongodb://127.0.0.1:27017/media%00files',
                $badName
            ],
            'null byte in the connection string' => [
                "mongodb://127.0.0.1:27017\0/media?tls=true",
                'The MongoDB connection string must not contain a NUL byte'
            ],
            'trailing null byte' => [
                "mongodb://127.0.0.1:27017/media\0",
                'The MongoDB connection string must not contain a NUL byte'
            ],
            'at sign in an option value' => [
                'mongodb://user:pass@127.0.0.1:27017?appName=a@b/media',
                $noDatabase
            ],
            'unencoded slash in the password' => [
                'mongodb://user:pa/ss@127.0.0.1:27017/media',
                'Percent-encode the reserved characters'
            ],
        ];
    }

    /**
     * The connection string carries the credentials, so no rejection may quote any part of it.
     *
     * @dataProvider rejectedConnectionStringWithPasswordDataProvider
     */
    public function testRejectionDoesNotExposeThePassword(string $dbConfig): void
    {
        try {
            new MongoDbDriverConfig($dbConfig);
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('s3cr3t', $exception->getMessage());

            return;
        }

        self::fail(sprintf('The connection string "%s" was expected to be rejected.', $dbConfig));
    }

    public function rejectedConnectionStringWithPasswordDataProvider(): array
    {
        return [
            'no database' => ['mongodb://user:s3cr3t@host:27017'],
            'missing slashes after the scheme' => ['mongodb:user:s3cr3t@host:27017/media'],
            'space in the database name' => ['mongodb://user:s3cr3t@host:27017/media files'],
            'nested path' => ['mongodb://user:s3cr3t@host:27017/media/files'],
        ];
    }

    /**
     * The driver quotes the whole connection string in a parse error, so the log entry must not
     * repeat it. The connection string below is rejected while the driver parses it, so the test
     * needs neither a server nor a name lookup.
     */
    public function testIsConnectedLogsTheFailureWithoutTheConnectionString(): void
    {
        $dbConfig = 'mongodb://:s3cr3t@host:27017/media';
        $config = new MongoDbDriverConfig($dbConfig);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'MongoDB ping failed.',
                self::callback(static function (array $context) use ($dbConfig): bool {
                    // The exception object is withheld on purpose: its trace holds the string.
                    self::assertSame(['error'], array_keys($context));
                    self::assertStringNotContainsString($dbConfig, $context['error']);
                    self::assertStringNotContainsString('s3cr3t', $context['error']);

                    return true;
                })
            );

        self::assertFalse($config->isConnected($logger));
        self::assertFalse($config->isConnected());
    }
}
