<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use App\Document\Domain\Document;
use Yiisoft\Yii\View\Renderer\Csrf;
use Yiisoft\Html\Html;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var ApplicationParams $applicationParams
 * @var Csrf|null $csrf
 * @var list<Document> $documents
 * @var string $queueDriver
 * @var int $workers
 * @var string $extractorAdapter
 * @var string $llmAdapter
 * @var string $llmModel
 * @var string $storageDriver
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
        <div class="demo-runtime">
            <div class="demo-runtime-item">
                <span><?= Html::encode(strtoupper($queueDriver)) ?></span>
                <small>queue driver</small>
            </div>
            <div class="demo-runtime-item">
                <span><?= $workers ?></span>
                <small>workers</small>
            </div>
            <div class="demo-runtime-item">
                <span><?= Html::encode($extractorAdapter) ?></span>
                <small>extractor</small>
            </div>
            <div class="demo-runtime-item">
                <span><?= Html::encode($llmAdapter) ?></span>
                <small>LLM adapter</small>
            </div>
            <div class="demo-runtime-item demo-runtime-item-wide">
                <span><?= Html::encode($llmModel) ?></span>
                <small>LLM model</small>
            </div>
            <div class="demo-runtime-item">
                <span><?= Html::encode($storageDriver) ?></span>
                <small>document storage driver</small>
            </div>
        </div>
    </div>

    <?php if ($errors !== []): ?>
        <div class="notice notice-error">
            <?php foreach ($errors as $error): ?>
                <div><?= Html::encode($error) ?></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <div class="upload-panel">
        <form class="upload-form" action="/documents/upload" method="post" enctype="multipart/form-data" data-upload-form>
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
    </div>

    <div class="document-table-wrap">
        <table class="document-table">
            <thead>
            <tr>
                <th>Document</th>
                <th>Status</th>
                <th>Progress</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr>
                    <td colspan="3" class="empty">No documents uploaded yet.</td>
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
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
