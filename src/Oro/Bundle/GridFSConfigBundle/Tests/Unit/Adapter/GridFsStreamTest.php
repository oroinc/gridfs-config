<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Adapter;

use Gaufrette\StreamMode;
use MongoDB\BSON\UTCDateTime;
use Oro\Bundle\GridFSConfigBundle\GridFS\Bucket;
use Oro\Bundle\GridFSConfigBundle\GridFS\GridFsStream;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GridFsStreamTest extends TestCase
{
    /** @dataProvider streamOpenDataProvider */
    public function testStreamOpen(
        string $key,
        StreamMode $mode,
        array $finded,
        bool $expected
    ): void {
        $bucket = $this->getBucketMock($key, $finded);
        $stream = new GridFsStream($bucket, $key);

        self::assertEquals($expected, $stream->open($mode));
    }

    public function streamOpenDataProvider(): array
    {
        return [
            'Normal open stream' => [
                'key' => 'testKey',
                'mode' => new StreamMode('r'),
                'finded' => [
                    '_id' => uniqid(),
                    'uploadDate' => new UTCDateTime(),
                    'length' => 1,
                    'metadata' => []
                ],
                'expected' => true,
            ],
            'Open stream on write with not allow to open existing file' => [
                'key' => 'testKey',
                'mode' => new StreamMode('x'),
                'finded' => [
                    '_id' => uniqid(),
                    'uploadDate' => new UTCDateTime(),
                    'length' => 1,
                    'metadata' => []
                ],
                'expected' => false,
            ],
            'Open stream on read with not allow to read not existing file' => [
                'key' => 'testKey',
                'mode' => new StreamMode('r'),
                'finded' => [],
                'expected' => false,
            ]
        ];
    }

    /** @dataProvider fileProvider */
    public function testRead(string $file): void
    {
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn(fopen($path, 'r'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $readed = $stream->read($lenth);

        self::assertTrue($stream->eof());
        self::assertEquals($lenth, $stream->tell());
        self::assertEquals($content, $readed);
    }

    /** @dataProvider fileProvider */
    public function testReadPartials(string $file): void
    {
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $batch = intval($lenth / 5);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn(fopen($path, 'r'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $readed = '';

        while (!$stream->eof()) {
            $readed .= $stream->read($batch);
        }

        self::assertTrue($stream->eof());
        self::assertEquals($lenth, $stream->tell());
        self::assertEquals($content, $readed);
    }

    public function testReadWithNegativeCount(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->read(-10);

        self::assertEquals('', $result);
    }

    public function testReadWithZeroCount(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->read(0);

        self::assertEquals('', $result);
    }

    public function testReadWithoutReadMode(): void
    {
        $file = 'test.txt';
        $bucket = $this->getBucketMock($file, []);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));

        $result = $stream->read(10);

        self::assertEquals('', $result);
    }

    /** @dataProvider fileProvider */
    public function testWrite(string $file, string $mimeType): void
    {
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => null,
            'uploadDate' => null,
            'length' => 0,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openUploadStream')
            ->with($file, ['contentType' => $mimeType])
            ->willReturn(fopen('php://temp', 'w+b'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));

        self::assertEquals($lenth, $stream->write($content));
    }

    /** @dataProvider fileProvider */
    public function testWriteExistingFile(string $file, string $mimeType): void
    {
        $id = uniqid();
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => $id,
            'uploadDate' => new UTCDateTime(),
            'length' => 10000,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('delete')
            ->with($id);
        $bucket->expects(self::once())
            ->method('openUploadStream')
            ->with($file, ['contentType' => $mimeType])
            ->willReturn(fopen('php://temp', 'w+b'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));

        self::assertEquals($lenth, $stream->write($content));
    }

    public function testWriteWithoutWriteMode(): void
    {
        $file = 'test.txt';
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => 100,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->write('test content');

        self::assertEquals(0, $result);
    }

    public function fileProvider(): array
    {
        return [
            ['test.gif', 'image/gif'],
            ['test.ico', 'application/ico'],
            ['test.jpeg', 'image/jpeg'],
            ['test.jpg', 'image/jpeg'],
            ['test.jxr', 'image/jxr'],
            ['test.pdf', 'application/pdf'],
            ['test.png', 'image/png'],
            ['test.rtf', 'application/rtf'],
            ['test.svg', 'image/svg+xml'],
            ['test.tga', 'application/tga'],
            ['test.ttf', 'application/x-font-truetype'],
            ['test.txt', 'text/plain'],
            ['test.webp', 'image/webp'],
            ['test.xml', 'application/xml'],
            ['test.mp4', 'video/mp4'],
        ];
    }

    public function testCloseReadSteam(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => 10000,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn(fopen($path, 'r'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));
        $stream->read($lenth);
        $stream->close();

        $reflection = new \ReflectionObject($stream);
        $reflectionProperty = $reflection->getProperty('readStream');

        self::assertNull($reflectionProperty->getValue($stream));
        self::assertEquals(0, $stream->tell());
    }

    public function testCloseWriteSteam(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $bucket = $this->getBucketMock($file, []);
        $bucket->expects(self::once())
            ->method('openUploadStream')
            ->with($file, ['contentType' => 'text/plain'])
            ->willReturn(fopen('php://temp', 'w+b'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));
        $stream->write($content);
        $stream->close();

        $reflection = new \ReflectionObject($stream);
        $reflectionProperty = $reflection->getProperty('writeStream');

        self::assertNull($reflectionProperty->getValue($stream));
        self::assertEquals(0, $stream->tell());
    }

    public function testFlush(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $bucket = $this->getBucketMock($file, []);
        $bucket->expects(self::once())
            ->method('openUploadStream')
            ->with($file, ['contentType' => 'text/plain'])
            ->willReturn(fopen('php://temp', 'w+b'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));
        $stream->write($content);

        $result = $stream->flush();

        self::assertTrue($result);
        self::assertEquals(0, $stream->tell());
    }

    public function testSeekSet(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->seek(10, SEEK_SET);

        self::assertTrue($result);
        self::assertEquals(10, $stream->tell());
    }

    public function testSeekCur(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn(fopen($path, 'r'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));
        $stream->read(5);

        $result = $stream->seek(10, SEEK_CUR);

        self::assertTrue($result);
        self::assertEquals(15, $stream->tell());
    }

    public function testSeekEnd(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->seek(-10, SEEK_END);

        self::assertTrue($result);
        self::assertEquals($lenth - 10, $stream->tell());
    }

    public function testSeekWithNegativeOffset(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->seek(-10);

        self::assertTrue($result);
        self::assertEquals(0, $stream->tell());
    }

    public function testTell(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn(fopen($path, 'r'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        self::assertEquals(0, $stream->tell());

        $stream->read(10);

        self::assertEquals(10, $stream->tell());
    }

    public function testEof(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $content = file_get_contents($path);
        $lenth = strlen($content);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => $lenth,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn(fopen($path, 'r'));
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        self::assertFalse($stream->eof());

        $stream->read($lenth);

        self::assertTrue($stream->eof());
    }

    public function testStatForExistingFile(): void
    {
        $file = 'test.txt';
        $dateTime = new \DateTime('2024-01-01 12:00:00');
        $uploadDate = new UTCDateTime($dateTime->getTimestamp() * 1000);
        $bucket = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => $uploadDate,
            'length' => 1024,
            'metadata' => []
        ]);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $stats = $stream->stat();

        self::assertIsArray($stats);
        self::assertEquals(1024, $stats['size']);
        self::assertEquals($dateTime->getTimestamp(), $stats['mtime']);
        self::assertEquals($dateTime->getTimestamp(), $stats['atime']);
        self::assertEquals($dateTime->getTimestamp(), $stats['ctime']);
    }

    public function testStatForNonExistingFile(): void
    {
        $file = 'test.txt';
        $bucket = $this->getBucketMock($file, []);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));

        $stats = $stream->stat();

        self::assertFalse($stats);
    }

    public function testUnlinkExistingFile(): void
    {
        $file = 'test.txt';
        $id = uniqid();
        $bucket = $this->getBucketMock($file, [
            '_id' => $id,
            'uploadDate' => new UTCDateTime(),
            'length' => 1024,
            'metadata' => []
        ]);
        $bucket->expects(self::once())
            ->method('delete')
            ->with($id);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('r'));

        $result = $stream->unlink();

        self::assertTrue($result);
    }

    public function testUnlinkNonExistingFile(): void
    {
        $file = 'test.txt';
        $bucket = $this->getBucketMock($file, []);
        $stream = new GridFsStream($bucket, $file);
        $stream->open(new StreamMode('w'));

        $result = $stream->unlink();

        self::assertFalse($result);
    }

    public function testCast(): void
    {
        $file = 'test.txt';
        $path = sprintf('%s/Fixtures/%s', __DIR__, $file);
        $bucket1 = $this->getBucketMock($file, []);
        $bucket2 = $this->getBucketMock($file, [
            '_id' => uniqid(),
            'uploadDate' => new UTCDateTime(),
            'length' => 10000,
            'metadata' => []
        ]);
        $writeStream = fopen('php://temp', 'w+b');
        $readStream = fopen($path, 'r');

        $bucket1->expects(self::once())
            ->method('openUploadStream')
            ->with($file, ['contentType' => 'text/plain'])
            ->willReturn($writeStream);
        $bucket2->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with($file)
            ->willReturn($readStream);

        $stream = new GridFsStream($bucket1, $file);
        $stream->open(new StreamMode('w'));

        self::assertEquals($writeStream, $stream->cast(1));

        $stream = new GridFsStream($bucket2, $file);
        $stream->open(new StreamMode('r'));

        self::assertEquals($readStream, $stream->cast(1));
    }

    private function getBucketMock(string $key, array $returnValue): Bucket&MockObject
    {
        $bucket = $this->createMock(Bucket::class);
        $bucket->expects(self::atMost(2))
            ->method('findOne')
            ->with(['filename' => $key], [
                'projection' => [
                    '_id' => 1,
                    'uploadDate' => 1,
                    'length' => 1,
                    'metadata' => 1
                ]
            ])
            ->willReturn(new \ArrayObject($returnValue));

        return $bucket;
    }
}
