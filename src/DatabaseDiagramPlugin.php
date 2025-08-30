<?php

namespace MyPackage\DatabaseDigram;

use App\Filament\Pages\DbDigram;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DatabaseDiagramPlugin implements Plugin
{
    public function getId(): string
    {
        return 'database-diagram';
    }

    public function register(Panel $panel): void
    {
        // هنا تضيف أي pages/resources
        $panel->pages([
            DbDigram::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
