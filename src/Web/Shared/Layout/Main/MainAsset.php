<?php

declare(strict_types=1);

namespace App\Web\Shared\Layout\Main;

use Yiisoft\Assets\AssetBundle;

/**
 * Registers the main stylesheet and script assets for the web layout.
 */
final class MainAsset extends AssetBundle
{
    public ?string $basePath = '@assets/main';
    public ?string $baseUrl = '@assetsUrl/main';
    public ?string $sourcePath = '@assetsSource/main';

    public array $css = [
        'site.css',
    ];

    public array $js = [
        'site.js',
    ];
}
