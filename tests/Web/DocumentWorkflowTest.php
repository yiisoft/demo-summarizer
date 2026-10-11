<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTestCase;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

use function file_put_contents;
use function is_file;
use function preg_match;
use function uniqid;
use function unlink;

#[Test]
final class DocumentWorkflowTest extends WebTestCase
{
    /** @var list<string> */
    private array $files = [];

    #[AfterTest]
    public function removeFiles(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function uploadViewDownloadAndDelete(): void
    {
        $name = 'workflow-' . uniqid() . '.md';
        $this->writeDataFile($name, "# Workflow\n\nA document uploaded by the browser workflow test.");

        $this->open('/');
        $this->upload($name);
        $this->seePath('/');
        $this->see($name);

        $href = $this->browser->getCrawler()->selectLink($name)->link()->getUri();
        $id = $this->documentId($href);

        $this->open('/documents/' . $this->documentId($href));
        $this->see($name);
        $this->see('completed');
        $this->see('Retry');
        $this->see('Events');
        $this->see('uploaded');
        $this->see('completed');
        $source = $this->browser->getResponse()->getContent();
        $completedPosition = strpos($source, '<strong>completed</strong>');
        $uploadedPosition = strpos($source, '<strong>uploaded</strong>');
        Assert::notSame($completedPosition, false);
        Assert::notSame($uploadedPosition, false);
        Assert::true($completedPosition < $uploadedPosition);

        $this->browser->submitForm('Retry');
        $this->seePath('/documents/' . $id);
        $this->see('retry');
        $this->see('completed');

        $this->open('/documents/' . $id . '/status');
        $this->see('"status":"completed"');

        $this->open('/documents/' . $id . '/download');
        $this->see('A document uploaded by the browser workflow test.');

        $this->open('/documents/' . $id . '/markdown');
        $this->see('A document uploaded by the browser workflow test.');

        $this->open('/documents/' . $this->documentId($href));
        $this->browser->submitForm('Delete');
        $this->seePath('/');
        $this->dontSee($name);
    }

    public function emptyUploadShowsBatchValidationMessage(): void
    {
        $this->open('/');
        $this->browser->submitForm('Upload');

        $this->see('Choose at least one document.');
        $this->dontSee('document #1 could not be uploaded.');
    }

    public function failedDocumentCanBeRetried(): void
    {
        $name = 'broken-' . uniqid() . '.pdf';
        $this->writeDataFile($name, "%PDF-1.4\nnot a valid pdf body\n%%EOF");

        $this->open('/');
        $this->upload($name);
        $this->seePath('/');
        $this->see($name);
        $this->see('failed');

        $href = $this->browser->getCrawler()->selectLink($name)->link()->getUri();
        $this->open('/documents/' . $this->documentId($href));
        $this->see('failed');
        $this->see('Retry');
        $this->browser->submitForm('Retry');

        $this->seePath('/documents/' . $this->documentId($href));
        $this->see($name);
        $this->see('failed');

        $this->open('/documents/' . $this->documentId($href));
        $this->see('retry');
        $this->see('failed');
    }

    public function clearAllDocuments(): void
    {
        $first = 'clear-first-' . uniqid() . '.md';
        $second = 'clear-second-' . uniqid() . '.txt';
        $this->writeDataFile($first, "# First\n\nDocument to clear.");
        $this->writeDataFile($second, "Second document to clear.");

        $this->open('/');
        $this->upload($first);
        $this->see($first);

        $this->upload($second);
        $this->see($second);

        $this->browser->submitForm('Clear all');
        $this->seePath('/');
        $this->dontSee($first);
        $this->dontSee($second);
        $this->see('No documents uploaded yet.');
    }


    private function upload(string $name): void
    {
        $form = $this->browser->getCrawler()->selectButton('Upload')->form();
        $form['documents'][0]->upload(sys_get_temp_dir() . '/' . $name);
        $this->browser->submit($form);
    }

    private function writeDataFile(string $name, string $contents): void
    {
        $path = sys_get_temp_dir() . '/' . $name;
        file_put_contents($path, $contents);
        $this->files[] = $path;
    }

    private function documentId(string $href): int
    {
        if (!preg_match('~/documents/(\d+)~', $href, $matches)) {
            throw new \RuntimeException('Unable to extract document ID from "' . $href . '".');
        }

        return (int) $matches[1];
    }
}
