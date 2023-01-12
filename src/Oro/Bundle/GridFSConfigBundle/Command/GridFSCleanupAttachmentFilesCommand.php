<?php

namespace Oro\Bundle\GridFSConfigBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Gaufrette\FilesystemMapInterface;
use MongoDB\BSON\Regex;
use Oro\Bundle\AttachmentBundle\Command\CleanupAttachmentFilesCommand;
use Oro\Bundle\GaufretteBundle\FileManager;
use Oro\Bundle\GridFSConfigBundle\Adapter\GridFS;

/**
 * The command to delete lost attachment files when the attachments are stored in GridFS.
 */
class GridFSCleanupAttachmentFilesCommand extends CleanupAttachmentFilesCommand
{
    protected static $defaultName = 'oro:attachment:cleanup-gridfs-files';

    private FilesystemMapInterface $filesystemMap;
    private string $filesystemName;

    public function __construct(
        int $collectAttachmentFilesBatchSize,
        int $loadAttachmentsBatchSize,
        int $loadExistingAttachmentsBatchSize,
        FileManager $dataFileManager,
        ManagerRegistry $doctrine,
        FileManager $attachmentFileManager,
        FilesystemMapInterface $filesystemMap,
        string $filesystemName
    ) {
        $this->filesystemMap = $filesystemMap;
        $this->filesystemName = $filesystemName;
        parent::__construct(
            $collectAttachmentFilesBatchSize,
            $loadAttachmentsBatchSize,
            $loadExistingAttachmentsBatchSize,
            $dataFileManager,
            $doctrine,
            $attachmentFileManager
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        return $this->getGridFSAdapter() !== null;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Deletes lost attachment files stored in GridFS.');
    }

    /**
     * {@inheritDoc}
     */
    protected function getAttachmentFileNames(): iterable
    {
        $cursor = $this->getGridFSAdapter()->getBucket()->find(
            ['filename' => new Regex(sprintf('^%s/', $this->filesystemName), '')],
            ['projection' => ['filename' => 1]]
        );
        foreach ($cursor as $file) {
            yield substr($file['filename'], \strlen($this->filesystemName) + 1);
        }
    }

    private function getGridFSAdapter(): ?GridFS
    {
        $adapter = $this->filesystemMap->get($this->filesystemName)->getAdapter();

        return $adapter instanceof GridFS
            ? $adapter
            : null;
    }
}
