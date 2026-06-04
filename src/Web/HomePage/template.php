<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;
use App\Document\DocumentDemoConfig;
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
 * @var DocumentDemoConfig $config
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
            <span><?= Html::encode(strtoupper($config->queueDriver)) ?></span>
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

    <form class="upload-panel" action="/documents/upload" method="post" enctype="multipart/form-data">
        <?= $csrf?->hiddenInput() ?>
        <input type="file" name="documents[]" multiple accept=".md,.txt,.html,.pdf,.docx">
        <button type="submit">Upload</button>
    </form>

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

<script>
if (document.querySelector('[data-poll="1"]')) {
    const poll = () => {
        document.querySelectorAll('[data-document-row]').forEach((row) => {
            const id = row.getAttribute('data-document-row');
            fetch(`/documents/${id}/status`)
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    if (!data) {
                        return;
                    }
                    const status = document.querySelector(`[data-status="${id}"]`);
                    const progress = document.querySelector(`[data-progress="${id}"]`);
                    const bar = document.querySelector(`[data-progress-bar="${id}"]`);
                    if (status) {
                        status.textContent = data.status;
                        status.className = `status status-${data.status}`;
                    }
                    if (progress) {
                        progress.textContent = `${data.progress}%`;
                    }
                    if (bar) {
                        bar.style.width = `${data.progress}%`;
                    }
                });
        });
    };
    setInterval(poll, 2000);
}
</script>
