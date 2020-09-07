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

    /**
     * @param Bucket $bucket
     */
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
        return parent::read(ltrim($key, '/'));
    }

    /**
     * {@inheritdoc}
     */
    public function write($key, $content)
    {
        if (empty($content)) {
            return 0;
        }

        if ($this->exists($key)) {
            $this->delete($key);
        }

        $stream = $this->getBucket()->openUploadStream(
            ltrim($key, '/'),
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
     * @return Bucket
     */
    public function getBucket(): Bucket
    {
        return $this->bucket;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function guessContentType(string $content): string
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        return $fileInfo->buffer($content);
    }
}
