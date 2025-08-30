<?php

namespace Hussain\DatabaseDiagram; // صحح "Digram" إلى "Diagram"

use Filament\Contracts\Plugin;
use Filament\Panel;
use Hussain\DatabaseDiagram\Filament\Pages\DbDigram;

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
