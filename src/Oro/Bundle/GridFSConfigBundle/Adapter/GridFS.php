<?php

namespace Oro\Bundle\GridFSConfigBundle\Adapter;

use Gaufrette\Adapter\GridFS as BaseGridFS;
use Oro\Bundle\GridFSConfigBundle\GridFS\Bucket;

/**
 * Gaufrette adapter for the GridFS filesystem on MongoDB database.
 *
 * For prevent duplicates in mongodb fs.files collection:
 * the same filename, but one with length=0, another - with real length, unique index on filename.
 * Removed "metadata" on write, added "contentType", removed begining "/":
 * required for nginx gridFs module for send content properly.
 */
class GridFS extends BaseGridFS
{
    /** @var Bucket */
    private $bucket;

    public function __construct(Bucket $bucket)
    {
        parent::__construct($bucket);
        $this->bucket = $bucket;
    }

    /**
     * {@inheritdoc}
     */
    public function read($key)
    {
        return parent::read($this->formatKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function write($key, $content)
    {
        if (empty($content)) {
            return 0;
        }

        // remove old file with same filename if one exist
        $this->delete($key);

        $stream = $this->getBucket()->openUploadStream(
            $this->formatKey($key),
            ['contentType' => $this->guessContentType($content)]
        );

        try {
            return fwrite($stream, $content);
        } finally {
            fclose($stream);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function exists($key)
    {
        return parent::exists($this->formatKey($key));
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
        return parent::delete($this->formatKey($key));
    }

    /**
     * {@inheritDoc}
     */
    public function rename($sourceKey, $targetKey)
    {
        return parent::rename($this->formatKey($sourceKey), $this->formatKey($targetKey));
    }

    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    protected function guessContentType(string $content): string
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        /**
         * According to:
         *  - https://en.wikipedia.org/wiki/File_signature
         *  - https://en.wikipedia.org/wiki/List_of_file_signatures
         *  - https://www.garykessler.net/library/file_sigs.html
         *
         * The first 1024 bytes of file should be enough for MimeType detection
         */
        return $fileInfo->buffer(substr($content, 0, 1024));
    }

    private function formatKey(string $key): string
    {
        return ltrim($key, '/');
    }
}
