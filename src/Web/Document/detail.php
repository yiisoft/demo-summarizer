<?php

declare(strict_types=1);

use App\Document\Domain\Document;
use App\Document\Domain\DocumentEvent;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var Document $document
 * @var list<DocumentEvent> $events
 * @var Csrf|null $csrf
 * @var UrlGeneratorInterface $urlGenerator
 */

$this->setTitle($document->originalName);
$homeUrl = $urlGenerator->generate('home');
$downloadUrl = $urlGenerator->generate('documents/download', ['id' => $document->id]);
$markdownUrl = $urlGenerator->generate('documents/markdown', ['id' => $document->id]);
$retryUrl = $urlGenerator->generate('documents/retry', ['id' => $document->id]);
$deleteUrl = $urlGenerator->generate('documents/delete', ['id' => $document->id]);
$statusUrl = $urlGenerator->generate('documents/status', ['id' => $document->id]);
$status = $document->status->value;
?>

<section
    class="demo-shell"
    data-poll="<?= $document->isActive() ? '1' : '0' ?>"
    data-document-detail="<?= $document->id ?>"
    data-status-url="<?= Html::encode($statusUrl) ?>"
    data-current-status="<?= Html::encode($status) ?>"
    data-refresh-on-terminal="1"
>
    <div class="detail-header">
        <div>
            <a href="<?= Html::encode($homeUrl) ?>">Back to documents</a>
            <h1><?= Html::encode($document->originalName) ?></h1>
        </div>
        <span class="status status-<?= Html::encode($status) ?>" data-status="<?= $document->id ?>">
            <?= Html::encode($status) ?>
        </span>
    </div>

    <div class="detail-grid">
        <section>
            <h2>Summary</h2>
            <div class="progress"><span data-progress-bar="<?= $document->id ?>" style="width: <?= $document->progress ?>%"></span></div>
            <small data-progress="<?= $document->id ?>"><?= $document->progress ?>%</small>

            <?php if ($document->summary !== null): ?>
                <pre class="summary"><?= Html::encode($document->summary) ?></pre>
            <?php elseif ($document->error !== null): ?>
                <div class="notice notice-error"><?= Html::encode($document->error) ?></div>
            <?php else: ?>
                <p>Processing has not produced a summary yet.</p>
            <?php endif ?>

            <div class="actions">
                <a href="<?= Html::encode($downloadUrl) ?>">Download original</a>
                <?php if ($document->markdownKey !== null): ?>
                    <a href="<?= Html::encode($markdownUrl) ?>">View markdown</a>
                <?php endif ?>
                <form action="<?= Html::encode($retryUrl) ?>" method="post">
                    <?= $csrf?->hiddenInput() ?>
                    <button type="submit">Retry</button>
                </form>
                <form action="<?= Html::encode($deleteUrl) ?>" method="post">
                    <?= $csrf?->hiddenInput() ?>
                    <button type="submit">Delete</button>
                </form>
            </div>
        </section>

        <aside>
            <h2>Events</h2>
            <ol class="timeline">
                <?php foreach ($events as $event): ?>
                    <li>
                        <strong><?= Html::encode($event->type) ?></strong>
                        <span><?= Html::encode($event->message) ?></span>
                        <small><?= Html::encode($event->createdAt) ?> · <?= $event->progress ?>%</small>
                    </li>
                <?php endforeach ?>
            </ol>
        </aside>
    </div>
</section>
