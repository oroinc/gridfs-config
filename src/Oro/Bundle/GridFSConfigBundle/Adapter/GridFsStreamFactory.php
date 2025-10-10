<?php

namespace Oro\Bundle\GridFSConfigBundle\Adapter;

use Gaufrette\Adapter\StreamFactory;
use Oro\Bundle\GridFSConfigBundle\GridFS\Bucket;
use Oro\Bundle\GridFSConfigBundle\GridFS\GridFsStream;

/**
 * Gaufrette adapter for the GridFS filesystem on MongoDB database that creates own stream.
 */
class GridFsStreamFactory extends GridFS implements StreamFactory
{
    public function __construct(private Bucket $bucket)
    {
        parent::__construct($bucket);
    }

    public function createStream($key)
    {
        return new GridFsStream($this->bucket, ltrim($key, '/'));
    }
}
