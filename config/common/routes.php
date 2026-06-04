<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->routes(
            Route::get('/')
                ->action(Web\HomePage\Action::class)
                ->name('home'),
            Route::post('/documents/upload')
                ->action(Web\Document\UploadAction::class)
                ->name('documents/upload'),
            Route::get('/documents/{id:\d+}')
                ->action(Web\Document\DetailAction::class)
                ->name('documents/detail'),
            Route::get('/documents/{id:\d+}/status')
                ->action(Web\Document\StatusAction::class)
                ->name('documents/status'),
            Route::get('/documents/{id:\d+}/download')
                ->action(Web\Document\DownloadAction::class)
                ->name('documents/download'),
            Route::get('/documents/{id:\d+}/markdown')
                ->action(Web\Document\MarkdownAction::class)
                ->name('documents/markdown'),
            Route::post('/documents/{id:\d+}/retry')
                ->action(Web\Document\RetryAction::class)
                ->name('documents/retry'),
            Route::post('/documents/{id:\d+}/delete')
                ->action(Web\Document\DeleteAction::class)
                ->name('documents/delete'),
        ),
];
