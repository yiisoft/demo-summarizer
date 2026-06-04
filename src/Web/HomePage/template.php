<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use App\Document\Domain\Document;
use App\Document\Domain\DocumentStatus;
use Yiisoft\Yii\View\Renderer\Csrf;
use Yiisoft\Html\Html;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var ApplicationParams $applicationParams
 * @var Csrf|null $csrf
 * @var list<Document> $documents
 * @var string $queueDriver
 * @var list<string> $errors
 */

$this->setTitle($applicationParams->name);
$hasActiveDocuments = false;
foreach ($documents as $document) {
    if ($document->isActive()) {
        $hasActiveDocuments = true;
        break;
    }
}
?>

<section class="demo-shell" data-poll="<?= $hasActiveDocuments ? '1' : '0' ?>">
    <div class="demo-header">
        <div>
            <h1>Document Summarizer</h1>
            <p>Upload documents, extract markdown, summarize content, and track queue progress.</p>
        </div>
        <div class="demo-mode">
            <span><?= Html::encode(strtoupper($queueDriver)) ?></span>
            <small>queue mode</small>
        </div>
    </div>

    <?php if ($errors !== []): ?>
        <div class="notice notice-error">
            <?php foreach ($errors as $error): ?>
                <div><?= Html::encode($error) ?></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <form class="upload-panel" action="/documents/upload" method="post" enctype="multipart/form-data" data-upload-form>
        <?= $csrf?->hiddenInput() ?>
        <label class="upload-dropzone" data-upload-dropzone>
            <input type="file" name="documents[]" multiple accept=".md,.txt,.html,.pdf,.docx" data-upload-input>
            <span class="upload-dropzone-title">Drop files here or browse</span>
            <span class="upload-dropzone-text">PDF, DOCX, Markdown, text, and HTML files are supported.</span>
            <span class="upload-file-list" data-upload-file-list>No files selected.</span>
        </label>
        <button type="submit">Upload</button>
    </form>

    <?php if ($documents !== []): ?>
        <form class="clear-panel" action="/documents/clear" method="post" data-confirm="Clear all documents, stored files, database records, and pending queue jobs?">
            <?= $csrf?->hiddenInput() ?>
            <button class="button-danger" type="submit">Clear all</button>
        </form>
    <?php endif ?>

    <div class="document-table-wrap">
        <table class="document-table">
            <thead>
            <tr>
                <th>Document</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr>
                    <td colspan="5" class="empty">No documents uploaded yet.</td>
                </tr>
            <?php endif ?>
            <?php foreach ($documents as $document): ?>
                <tr data-document-row="<?= $document->id ?>">
                    <td>
                        <a href="/documents/<?= $document->id ?>"><?= Html::encode($document->originalName) ?></a>
                        <small><?= Html::encode($document->extension) ?> · <?= number_format($document->byteSize / 1024, 1) ?> KB</small>
                    </td>
                    <td>
                        <span class="status status-<?= Html::encode($document->status) ?>" data-status="<?= $document->id ?>">
                            <?= Html::encode($document->status) ?>
                        </span>
                    </td>
                    <td>
                        <div class="progress"><span data-progress-bar="<?= $document->id ?>" style="width: <?= $document->progress ?>%"></span></div>
                        <small data-progress="<?= $document->id ?>"><?= $document->progress ?>%</small>
                    </td>
                    <td><?= Html::encode($document->updatedAt) ?></td>
                    <td class="actions">
                        <a href="/documents/<?= $document->id ?>">Open</a>
                        <a href="/documents/<?= $document->id ?>/download">Original</a>
                        <?php if ($document->markdownKey !== null): ?>
                            <a href="/documents/<?= $document->id ?>/markdown">Markdown</a>
                        <?php endif ?>
                        <?php if ($document->status === DocumentStatus::FAILED): ?>
                            <form action="/documents/<?= $document->id ?>/retry" method="post">
                                <?= $csrf?->hiddenInput() ?>
                                <button type="submit">Retry</button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
