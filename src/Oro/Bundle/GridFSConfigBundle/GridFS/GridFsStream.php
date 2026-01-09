<?php

namespace Oro\Bundle\GridFSConfigBundle\GridFS;

use Gaufrette\Stream;
use Gaufrette\StreamMode;
use MongoDB\GridFS\Bucket;
use Symfony\Component\Mime\MimeTypes;

/**
 * Stream for GridFS filesystem without buffering for avoiding memory overflow in @see \Gaufrette\Stream\InMemoryBuffer
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GridFsStream implements Stream
{
    private const DEFAULT_CONTENT_TYPE = 'text/plain';

    private ?StreamMode $mode = null;
    private $readStream;
    private $writeStream;
    private array $fileInfo = [];
    private int $offset = 0;

    public function __construct(private Bucket $bucket, private string $key)
    {
    }

    public function open(StreamMode $mode)
    {
        $this->mode = $mode;
        $this->fileInfo = $this->getFileInfo();
        $exists = $this->exists();

        if (
            ($exists && !$mode->allowsExistingFileOpening())
            || (!$exists && !$mode->allowsNewFileOpening())
        ) {
            return false;
        }

        return true;
    }

    public function read($count)
    {
        if (false === $this->mode?->allowsRead()) {
            return '';
        }

        if ($count < 0 || $count === 0) {
            return '';
        }

        $this->ensureReadStreamOpened();

        $length = $this->fileInfo['length'];

        if ($this->offset + $count > $length) {
            $count = $length - $this->offset;
        }

        $content = stream_get_contents($this->readStream, $count, $this->offset);
        if ($content === false) {
            return '';
        }

        $this->seek($count, SEEK_CUR);

        return $content;
    }

    public function write($data)
    {
        if (false === $this->mode?->allowsWrite()) {
            return 0;
        }

        $this->ensureWriteStreamOpened();

        $written = fwrite($this->writeStream, $data);

        if ($written === false) {
            return 0;
        }

        // Update offset after successful write.
        $this->offset += $written;

        return $written;
    }

    public function close()
    {
        $this->offset = 0;

        if (is_resource($this->writeStream)) {
            fclose($this->writeStream);
            $this->writeStream = null;
        }
        if (is_resource($this->readStream)) {
            fclose($this->readStream);
            $this->readStream = null;
        }

        return true;
    }

    public function flush()
    {
        return $this->close();
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        $newOffset = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->offset + $offset,
            SEEK_END => $this->fileInfo['length'] + $offset,
            default => $this->offset
        };

        if ($newOffset < 0) {
            $newOffset = 0;
        }

        $this->offset = $newOffset;

        return true;
    }

    public function tell()
    {
        return $this->offset;
    }

    public function eof()
    {
        return $this->offset >= $this->fileInfo['length'];
    }

    public function stat()
    {
        if ($this->exists()) {
            $time = (int)$this->fileInfo['uploadDate']->toDateTime()->format('U');

            $stats = [
                'dev' => 1,
                'ino' => 0,
                'mode' => 33204,
                'nlink' => 1,
                'uid' => 0,
                'gid' => 0,
                'rdev' => 0,
                'size' => $this->fileInfo['length'],
                'atime' => $time,
                'mtime' => $time,
                'ctime' => $time,
                'blksize' => -1,
                'blocks' => -1,
            ];

            return array_merge(array_values($stats), $stats);
        }

        return false;
    }

    public function cast($castAs)
    {
        if ($this->mode?->allowsRead()) {
            $this->ensureReadStreamOpened();

            return $this->readStream;
        } elseif ($this->mode?->allowsWrite()) {
            $this->ensureWriteStreamOpened();

            return $this->writeStream;
        }

        return false;
    }

    public function unlink()
    {
        if (!$this->exists()) {
            return false;
        }

        $this->bucket->delete($this->fileInfo['_id']);

        return true;
    }

    private function getFileInfo(): array
    {
        $info = $this->bucket->findOne(
            ['filename' => $this->key],
            [
                'projection' => [
                    '_id' => 1,
                    'uploadDate' => 1,
                    'length' => 1,
                    'metadata' => 1
                ]
            ]
        )?->getArrayCopy();

        if ($info) {
            $info['metadata'] = isset($info['metadata']) ? iterator_to_array($info['metadata']) : [];
        } else {
            $info = [
                '_id' => null,
                'length' => 0,
                'uploadDate' => null,
            ];
        }

        return $info;
    }

    private function ensureReadStreamOpened(): void
    {
        $exists = $this->exists();

        if ($exists && $this->mode?->allowsRead() && !is_resource($this->readStream)) {
            $this->readStream = $this->bucket->openDownloadStreamByName($this->key);
        }
    }

    private function ensureWriteStreamOpened(): void
    {
        // Early return if stream already opened.
        if (is_resource($this->writeStream)) {
            return;
        }

        if (!$this->mode->allowsWrite()) {
            return;
        }

        // Delete existing file before creating new upload stream.
        if ($this->exists()) {
            $this->unlink();
            $this->fileInfo = $this->getFileInfo();
            $this->offset = 0;
        }

        // Open new upload stream.
        $this->writeStream = $this->bucket->openUploadStream(
            $this->key,
            ['contentType' => $this->guessContentType()]
        );
    }

    protected function guessContentType(): string
    {
        $mimeTypes = new MimeTypes();
        $ext = pathinfo($this->key, PATHINFO_EXTENSION);
        $mimeType = current($mimeTypes->getMimeTypes($ext));

        return $mimeType ?: self::DEFAULT_CONTENT_TYPE;
    }

    private function exists(): bool
    {
        return $this->fileInfo['_id'] !== null;
    }
}
