<?php

namespace Hussain\DatabaseDiagram; // صحح "Digram" إلى "Diagram"

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider as LaravelPackageToolsPackageServiceProvider;

class PackageServiceProvider extends LaravelPackageToolsPackageServiceProvider
{
    public static string $name = 'database-diagram';

    /**
     * Configure your package
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()       // ينشر ملف config إذا عندك
            ->hasViews()            // يدعم ال views
            ->hasTranslations()     // يدعم lang
            ->hasMigrations();      // يحمّل migrations
    }

    /**
     * Boot logic after package loaded
     */
    public function packageBooted(): void
    {
        // هنا تكدر تستعمل laravel-erd أو تسجّل أي شي خاص بك
        // مثال: إضافة route
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
