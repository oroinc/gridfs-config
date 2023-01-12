<?php

namespace Oro\Bundle\GridFSConfigBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\GaufretteBundle\FileManager;
use Oro\Bundle\GridFSConfigBundle\Adapter\GridFS;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\Command\CommandTestingTrait;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

/**
 * @dbIsolationPerTest
 */
class GridFSCleanupAttachmentFilesCommandTest extends WebTestCase
{
    use CommandTestingTrait;

    private bool $lostAttachmentFilesRemoved = false;

    protected function setUp(): void
    {
        $this->initClient();

        $adapter = self::getContainer()->get('knp_gaufrette.filesystem_map')
            ->get('attachments')
            ->getAdapter();
        if (!$adapter instanceof GridFS) {
            self::markTestSkipped('This test is applicable only for GridFS.');
        }

        if (!$this->lostAttachmentFilesRemoved) {
            // remove all lost attachment files to avoid failure of this test
            // such lost files can exists in GridFS due to we do not have GridFS isolation in functional tests,
            // as result other tests can add but do not remove them after tests finished,
            // e.g. such files can be added by data fixtures and there is no easy way to remove them
            $attachmentFileManager = $this->getAttachmentFileManager();
            $fileNames = $attachmentFileManager->findFiles();
            $existingAttachments = $this->loadExistingAttachments($fileNames);
            foreach ($fileNames as $fileName) {
                if (!isset($existingAttachments[$fileName])) {
                    $attachmentFileManager->deleteFile($fileName);
                }
            }
            $this->lostAttachmentFilesRemoved = true;
        }
    }

    private function loadExistingAttachments(array $fileNames): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->from(File::class, 'f')
            ->select('f.filename')
            ->where('f.filename IN (:fileNames)')
            ->setParameter('fileNames', $fileNames)
            ->getQuery()
            ->getArrayResult();
        $existingAttachments = [];
        foreach ($rows as $row) {
            $existingAttachments[$row['filename']] = true;
        }

        return $existingAttachments;
    }

    private function getDataFileManager(): FileManager
    {
        return self::getContainer()->get('oro_attachment.file_manager.cleanup_data');
    }

    private function getAttachmentFileManager(): FileManager
    {
        return self::getContainer()->get('oro_attachment.file_manager');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManagerForClass(File::class);
    }

    private function createFile(): File
    {
        $em = $this->getEntityManager();

        $file = new File();
        $file->setFile(new SymfonyFile(__DIR__ . '/DataFixtures/files/empty.jpg'));
        $file->setOriginalFilename('empty.jpg');
        $file->setFilename('empty.jpg');
        $file->setParentEntityClass('Test\Entity');
        $file->setParentEntityFieldName('testField');
        $file->setParentEntityId(1);
        $em->persist($file);
        $em->flush();

        return $file;
    }

    private function updateFileName(File $file, string $fileName): void
    {
        $this->getEntityManager()
            ->createQueryBuilder()
            ->update(File::class, 'f')
            ->set('f.filename', ':filename')
            ->where('f.id = :id')
            ->setParameter('id', $file->getId())
            ->setParameter('filename', $fileName)
            ->getQuery()
            ->execute();
    }

    public function testCommandWithoutOptions(): void
    {
        $commandTester = $this->doExecuteCommand('oro:attachment:cleanup-gridfs-files');

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'To force execution run command with --force option');
    }

    public function testCommandWithForceOptionWithoutLostAttachmentFiles(): void
    {
        $commandTester = $this->doExecuteCommand('oro:attachment:cleanup-gridfs-files', ['--force' => true]);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'Collecting attachment files to cleanup...');
        $this->assertOutputContains($commandTester, 'The number of batches to be be processed: 1');
        $this->assertOutputContains($commandTester, 'Cleaning up attachment files...');
        $this->assertOutputContains($commandTester, '[OK] The attachment files were successfully cleaned up.');

        self::assertEmpty($this->getDataFileManager()->findFiles());
    }

    public function testCommandWithForceOptionWithLostAttachmentFiles(): void
    {
        $file = $this->createFile();
        $fileName = $file->getFilename();
        $this->updateFileName($file, 'test_lost_file.txt');

        $commandTester = $this->doExecuteCommand('oro:attachment:cleanup-gridfs-files', ['--force' => true]);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'Collecting attachment files to cleanup...');
        $this->assertOutputContains($commandTester, 'The number of batches to be be processed: 1');
        $this->assertOutputContains($commandTester, 'Cleaning up attachment files...');
        $this->assertOutputContains($commandTester, '[OK] The attachment files were successfully cleaned up.');

        $this->assertOutputContains(
            $commandTester,
            sprintf(
                '[warning] The attachment file "%s" has been removed'
                . ' because it does not have linked attachment entity in the database.',
                $fileName
            )
        );

        self::assertEmpty($this->getDataFileManager()->findFiles());
        self::assertEmpty($this->getAttachmentFileManager()->hasFile($fileName));
    }

    public function testCommandWithDryRunOptionWithoutLostAttachmentFiles(): void
    {
        $commandTester = $this->doExecuteCommand('oro:attachment:cleanup-gridfs-files', ['--dry-run' => true]);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'Collecting attachment files to cleanup...');
        $this->assertOutputContains(
            $commandTester,
            'Checking if attachment files are linked to existing attachment entities...'
        );
        $this->assertOutputContains(
            $commandTester,
            'Checking if attachment entities are linked to existing attachment files...'
        );
        $this->assertOutputContains($commandTester, '[OK] The attachment files to be cleaned up were not found.');

        self::assertEmpty($this->getDataFileManager()->findFiles());
    }

    public function testCommandWithDryRunOptionAndWithLostAttachmentFiles(): void
    {
        $file = $this->createFile();
        $fileName = $file->getFilename();
        $newFileName = 'test_lost_file.txt';
        $this->updateFileName($file, $newFileName);

        try {
            $commandTester = $this->doExecuteCommand('oro:attachment:cleanup-gridfs-files', ['--dry-run' => true]);
        } finally {
            $this->getAttachmentFileManager()->deleteFile($fileName);
        }

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertOutputContains($commandTester, 'Collecting attachment files to cleanup...');
        $this->assertOutputContains(
            $commandTester,
            'Checking if attachment files are linked to existing attachment entities...'
        );
        $this->assertOutputContains(
            $commandTester,
            'Checking if attachment entities are linked to existing attachment files...'
        );
        $this->assertOutputContains(
            $commandTester,
            sprintf(
                '[warning] The attachment file "%s" should be removed'
                . ' because it does not have linked attachment entity in the database.',
                $fileName
            )
        );
        $this->assertOutputContains(
            $commandTester,
            sprintf(
                '[warning] The attachment entity with ID = %d (entity: %s, field: %s)'
                . ' is linked to the file "%s" but this file does not exist.',
                $file->getId(),
                $file->getParentEntityClass(),
                $file->getParentEntityFieldName(),
                $newFileName
            )
        );

        self::assertEmpty($this->getDataFileManager()->findFiles());
    }
}
