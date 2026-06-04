<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Support\WebTester;

use function file_put_contents;
use function is_file;
use function preg_match;
use function uniqid;
use function unlink;

final class DocumentWorkflowCest
{
    /** @var list<string> */
    private array $files = [];

    public function _after(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function uploadViewDownloadAndDelete(WebTester $I): void
    {
        $name = 'workflow-' . uniqid() . '.md';
        $this->writeDataFile($name, "# Workflow\n\nA document uploaded by the browser workflow test.");

        $I->amOnPage('/');
        $I->attachFile('documents[]', $name);
        $I->click('Upload');
        $I->seeInCurrentUrl('/');
        $I->see($name);

        $href = $I->grabAttributeFrom('//a[text()="' . $name . '"]', 'href');
        $id = $this->documentId($href);

        $I->amOnPage($href);
        $I->see($name);
        $I->see('completed');
        $I->see('Events');
        $I->see('uploaded');
        $I->see('completed');

        $I->amOnPage('/documents/' . $id . '/status');
        $I->see('"status":"completed"');

        $I->amOnPage('/documents/' . $id . '/download');
        $I->see('A document uploaded by the browser workflow test.');

        $I->amOnPage('/documents/' . $id . '/markdown');
        $I->see('A document uploaded by the browser workflow test.');

        $I->amOnPage($href);
        $I->click('Delete');
        $I->seeInCurrentUrl('/');
        $I->dontSee($name);
    }

    public function failedDocumentCanBeRetried(WebTester $I): void
    {
        $name = 'broken-' . uniqid() . '.docx';
        $this->writeDataFile($name, "PK\nnot a valid docx archive");

        $I->amOnPage('/');
        $I->attachFile('documents[]', $name);
        $I->click('Upload');
        $I->seeInCurrentUrl('/');
        $I->see($name);
        $I->see('failed');

        $href = $I->grabAttributeFrom('//a[text()="' . $name . '"]', 'href');
        $I->amOnPage($href);
        $I->see('failed');
        $I->see('Retry');
        $I->click('Retry');

        $I->seeInCurrentUrl('/');
        $I->see($name);
        $I->see('failed');

        $I->amOnPage($href);
        $I->see('retry');
        $I->see('failed');
    }

    private function writeDataFile(string $name, string $contents): void
    {
        $path = __DIR__ . '/../Support/Data/' . $name;
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
