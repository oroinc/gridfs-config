<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Adapter;

use Oro\Bundle\GridFSConfigBundle\Adapter\GridFS;
use Oro\Bundle\GridFSConfigBundle\GridFS\Bucket;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GridFSTest extends \PHPUnit\Framework\TestCase
{
    /** @var GridFS */
    private $gridFSAdapter;

    /** @var Bucket|MockObject */
    private $mongoDBBucketMock;

    protected function setUp(): void
    {
        $this->mongoDBBucketMock = $this->createMock(Bucket::class);

        $this->gridFSAdapter = new GridFS($this->mongoDBBucketMock);
    }

    public function testWriteParentNotCalledWithEmptyContent()
    {
        $this->mongoDBBucketMock
            ->expects(self::never())
            ->method('openUploadStream');

        $this->gridFSAdapter->write('test', '');
    }

    public function testWriteOnExistingKey()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('findOne')
            ->with(['filename' => 'test.txt'])
            ->willReturn(['_id' => '5f57c695ac49b642ae71f12c', 'data' => 'some data', 'filename' => 'test.txt']);

        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('delete')
            ->with('5f57c695ac49b642ae71f12c');

        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('openUploadStream')
            ->with(
                'test.txt',
                ['contentType' => 'text/plain']
            )
            ->willReturn(fopen('php://temp', 'w+b'));

        self::assertEquals(17, $this->gridFSAdapter->write('/test.txt', 'not empty content'));
    }

    public function testWriteOnNonExistingKey()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('findOne')
            ->willReturn(null);

        $this->mongoDBBucketMock
            ->expects(self::never())
            ->method('delete');

        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('openUploadStream')
            ->with(
                'test',
                ['contentType' => 'text/plain']
            )
            ->willReturn(fopen('php://temp', 'w+b'));

        self::assertEquals(17, $this->gridFSAdapter->write('test', 'not empty content'));
    }

    public function testTryToWriteOnExceptionDuringWrite()
    {
        $this->mongoDBBucketMock
            ->expects(self::any())
            ->method('findOne')
            ->with(['filename' => 'test'])
            ->willReturn(null);

        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('openUploadStream')
            ->with(
                'test',
                ['contentType' => 'text/plain']
            )
            ->willReturn(fopen('php://temp', 'r'));

        self::assertFalse($this->gridFSAdapter->write('test', 'not empty content'));
    }

    public function testRead()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('openDownloadStreamByName')
            ->with('test.txt')
            ->willReturn(fopen(__DIR__ . '/Fixtures/test.txt', 'r'));

        $expectedContent = "some text\n";

        self::assertEquals($expectedContent, $this->gridFSAdapter->read('/test.txt'));
    }

    public function testExistsWithExistFile()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('findOne')
            ->with(['filename' => 'test.txt'])
            ->willReturn(['_id' => '5f57c695ac49b642ae71f12c', 'data' => 'some data', 'filename' => 'test.txt']);

        self::assertTrue($this->gridFSAdapter->exists('/test.txt'));
    }

    public function testExistsWithNonExistFile()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('findOne')
            ->with(['filename' => 'test.txt'])
            ->willReturn(null);

        self::assertFalse($this->gridFSAdapter->exists('/test.txt'));
    }

    public function testDeleteOnExistingFile()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('findOne')
            ->with(['filename' => 'test.txt'])
            ->willReturn(['_id' => '5f57c695ac49b642ae71f12c', 'data' => 'some data', 'filename' => 'test.txt']);

        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('delete')
            ->with('5f57c695ac49b642ae71f12c');

        self::assertTrue($this->gridFSAdapter->delete('/test.txt'));
    }

    public function testDeleteOnNonExistingFile()
    {
        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('findOne')
            ->with(['filename' => 'test.txt'])
            ->willReturn(null);

        $this->mongoDBBucketMock
            ->expects(self::never())
            ->method('delete');

        self::assertFalse($this->gridFSAdapter->delete('/test.txt'));
    }

    public function testGetBucket()
    {
        self::assertEquals($this->mongoDBBucketMock, $this->gridFSAdapter->getBucket());
    }

    /**
     * @dataProvider fileContentProvider
     */
    public function testGuessContentType($content, $expectedMimeType)
    {
        $this->mongoDBBucketMock->expects(self::once())
            ->method('findOne')
            ->willReturn(null);
        $this->mongoDBBucketMock->expects(self::once())
            ->method('openUploadStream')
            ->with('test', ['contentType' => $expectedMimeType])
            ->willReturn(fopen('php://temp', 'r'));

        $this->gridFSAdapter->write('test', $content);
    }

    public function fileContentProvider(): array
    {
        return [
            [file_get_contents(__DIR__ . '/Fixtures/test.gif'), 'image/gif'],
            [file_get_contents(__DIR__ . '/Fixtures/test.ico'), 'image/vnd.microsoft.icon'],
            [file_get_contents(__DIR__ . '/Fixtures/test.jpeg'), 'image/jpeg'],
            [file_get_contents(__DIR__ . '/Fixtures/test.jpg'), 'image/jpeg'],
            [file_get_contents(__DIR__ . '/Fixtures/test.jxr'), 'image/jxr'],
            [file_get_contents(__DIR__ . '/Fixtures/test.pdf'), 'application/pdf'],
            [file_get_contents(__DIR__ . '/Fixtures/test.png'), 'image/png'],
            [file_get_contents(__DIR__ . '/Fixtures/test.rtf'), 'text/rtf'],
            [file_get_contents(__DIR__ . '/Fixtures/test.svg'), 'image/svg+xml'],
            [file_get_contents(__DIR__ . '/Fixtures/test.tga'), 'image/x-tga'],
            [file_get_contents(__DIR__ . '/Fixtures/test.ttf'), 'font/sfnt'],
            [file_get_contents(__DIR__ . '/Fixtures/test.txt'), 'text/plain'],
            [file_get_contents(__DIR__ . '/Fixtures/test.webp'), 'image/webp'],
            [file_get_contents(__DIR__ . '/Fixtures/test.xml'), 'text/plain'],
            [file_get_contents(__DIR__ . '/Fixtures/test.mp4'), 'video/mp4'],
        ];
    }
}
