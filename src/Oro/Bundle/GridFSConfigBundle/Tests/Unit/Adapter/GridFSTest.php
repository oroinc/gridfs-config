<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Unit\Adapter;

use Oro\Bundle\GridFSConfigBundle\Adapter\GridFS;
use Oro\Bundle\GridFSConfigBundle\GridFS\Bucket;
use PHPUnit\Framework\MockObject\MockObject;

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
            ->expects(self::any())
            ->method('findOne')
            ->with(['filename' => 'test'])
            ->willReturn(['_id' => 'test', 'data' => 'some data']);

        $this->mongoDBBucketMock
            ->expects(self::once())
            ->method('delete')
            ->with('test');

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

    public function testWriteOnNonExistingKey()
    {
        $this->mongoDBBucketMock
            ->expects(self::any())
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

    public function testGetBucket()
    {
        self::assertEquals($this->mongoDBBucketMock, $this->gridFSAdapter->getBucket());
    }
}
