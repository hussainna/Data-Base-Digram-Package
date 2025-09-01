<?php

namespace Hussain\DatabaseDiagram\Filament\Pages;

use Recca0120\LaravelERD\ERD;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Recca0120\LaravelErd\Table;
use Recca0120\LaravelErd\Contracts\TableSchema;
use Illuminate\Support\Str;

class DbDigram extends Page
{
    protected string $view = 'filament-database-digram::pages.db-digram';

    public $config = null;
    public $extension = null;
    public $storagePath = null;
    public $file = null;
    public $path = null;
    public $jsValue;
    // public $view = null;

    public $sqlContent;

    protected $listeners = ['receiveSqlContent'];

    public function mount()
    {
        $this->config = config('laravel-erd');
        $this->storagePath = $this->config['storage_path'];
        $this->file = $this->file ?? config('database.default');
        $this->file = ! File::extension($this->file) ? $this->file . '.' . ($this->config['extension'] ?? 'sql') : $this->file;
        $this->extension = File::extension($this->file);

        $this->path = $this->storagePath . '/' . $this->file;
        $view = $this->extension === 'svg' ? 'svg' : 'erd-editor';

        abort_unless(File::exists($this->path), 404);
    }

    public function saveSql($content)
    {
        // Generate migration file name
        $timestamp = date('Y_m_d_His');
        $migrationName = 'converted_sql_migration';
        $randomString = Str::lower(Str::random(6)) . rand(1000, 9999);

        // Convert SQL → Laravel Schema code
        $laravelSchemaCode = $this->parseFullSchema($content);


        foreach ($laravelSchemaCode as $table => $value) {
            // Build migration file
            $columnExport = var_export(array_slice($value['columns'], 1), true);
            $relationshipsExport = var_export(array_slice($value['relationships'], 1), true);
            $fileName = database_path("migrations/{$timestamp}_{$table}_{$randomString}.php");

            $migrationContent = <<<PHP
        <?php
        
        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;
        use Illuminate\\Support\\Facades\\DB;
        
        return new class extends Migration
        {
            public function up(): void
            {
                
                \$columnExport = $columnExport;
                \$relationshipsExport = $relationshipsExport;
                

        
        
                    if (!Schema::hasTable("$table")) {
                        // 1️⃣ Create table if not exists
                        Schema::create("$table", function (Blueprint \$table) use (\$columnExport,\$relationshipsExport) {
        PHP;

            $migrationContent .= <<<PHP

                \$this->addColumns(\$table, \$columnExport);
                \$this->addRelationships(\$table, \$relationshipsExport ?? []);

            });
        } else {
            // 2️⃣ Table exists: sync columns
            \$existingColumns = Schema::getColumnListing('{$value['table_name']}');

                            \$allColumns = array_unique(array_merge(
                array_column(\$columnExport, 'name'),
                \$existingColumns
            ));

            foreach (\$allColumns as \$colName) {
                // Check if column is in export definition
                \$exportCol = collect(\$columnExport)->firstWhere('name', \$colName);

                \$inExport  = \$exportCol !== null;
                \$inDB      = in_array(\$colName, \$existingColumns);

                if (\$inDB && !\$inExport) {
                    // 1. Delete (exists in DB but not in export)
                    Schema::table('{$value['table_name']}', function (Blueprint \$table) use (\$colName) {
                        \$table->dropColumn(\$colName);
                    });

                } elseif (!\$inDB && \$inExport) {
                    // 2. Create (exists in export but not in DB)
                    Schema::table('{$value['table_name']}', function (Blueprint \$table) use (\$exportCol) {
                        if (preg_match('/^varchar\(\d+\)\$/i', \$exportCol['type'])) {
                            \$col = \$table->string(\$exportCol['name']);
                        } else if(preg_match('/^integer\(\d+\)\$/i', \$exportCol['type'])){
                            \$col = \$table->integer(\$exportCol['name']);
                        }
                        else{
                                \$col = \$table->{\$exportCol['type']}(\$exportCol['name']);
                            }

                        if (\$exportCol['nullable']) {
                            \$col->nullable();
                        }
                    });

                } elseif (\$inDB && \$inExport) {
                    // 3. Update (exists in both)
                    \$this->updateColumn('{$value['table_name']}', \$exportCol);
                }
            }
            }

    PHP;


            $migrationContent .= <<<PHP

            \$this->syncRelationships('{$value['table_name']}', \$relationshipsExport ?? []);

        }

        public function down(): void
    {
        // Drop tables in reverse order if needed
    }

    private function addColumns(Blueprint \$table, array \$columns)
    {
        foreach (\$columns as \$column) {
        if(preg_match('/^varchar\(\d+\)$/i', \$column['type']))
        {
            \$col = \$table->string(\$column['name']);
        }
            \$col = \$table->{\$column['type']}(\$column['name']);
            
            if (!empty(\$column['nullable'])) \$col->nullable();
            
        }
    }

    private function addRelationships(Blueprint \$table, array \$relationships)
    {
        foreach (\$relationships as \$rel) {
            if (\$rel['type'] === 'belongsTo') {
                \$table->foreign(\$rel['foreign_key'])
                      ->references(\$rel['local_key'])
                      ->on(\$rel['table'])
                      ->onDelete(\$rel['onDelete'] ?? 'cascade')
                      ->onUpdate(\$rel['onUpdate'] ?? 'cascade');
            }
        }
    }

private function updateColumn(string \$tableName, array \$column)
{
    \$driver = DB::getDriverName();

    \$name = \$column['name'];
    \$nullable = !empty(\$column['nullable']) ? 'DROP NOT NULL' : 'SET NOT NULL';
    \$default = isset(\$column['default']) ? "SET DEFAULT '{\$column['default']}'" : "DROP DEFAULT";

    if (\$driver === 'mysql') {
        \$type = \$column['type'];
        \$nullableSql = !empty(\$column['nullable']) ? 'NULL' : 'NOT NULL';
        \$defaultSql = isset(\$column['default']) ? "DEFAULT '{\$column['default']}'" : '';
        \$sql = "ALTER TABLE {\$tableName} MODIFY COLUMN {\$name} {\$type} {\$nullableSql} {\$defaultSql}";
        DB::statement(\$sql);
    } elseif (\$driver === 'pgsql') {
        switch (\$column['type']) {
            case 'integer(11)':
                DB::statement("ALTER TABLE {\$tableName} ALTER COLUMN \"{\$name}\" TYPE integer");
                break;
            case 'varchar(255)':
                DB::statement("ALTER TABLE {\$tableName} ALTER COLUMN \"{\$name}\" TYPE varchar(255)");
                break;
            case 'text':
                DB::statement("ALTER TABLE {\$tableName} ALTER COLUMN \"{\$name}\" TYPE text");
                break;
            case 'datetime':
                DB::statement("ALTER TABLE {\$tableName} ALTER COLUMN \"{\$name}\" TYPE timestamp");
                break;
        }

        DB::statement("ALTER TABLE {\$tableName} ALTER COLUMN \"{\$name}\" {\$nullable}");
        DB::statement("ALTER TABLE {\$tableName} ALTER COLUMN \"{\$name}\" {\$default}");
    }
}



    private function syncRelationships(string \$tableName, array \$relationships)
{
    \$driver = DB::getDriverName();

    // Get existing foreign keys depending on the driver
    if (\$driver === 'mysql') {
        \$existingFKs = DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = '{\$tableName}' AND CONSTRAINT_SCHEMA = DATABASE()
        ");
        \$existingFKs = collect(\$existingFKs)->pluck('CONSTRAINT_NAME')->toArray();
    } elseif (\$driver === 'pgsql') {
        \$existingFKs = DB::select("
            SELECT constraint_name
            FROM information_schema.key_column_usage
            WHERE table_name = '{\$tableName}' AND table_catalog = current_database()
        ");
        \$existingFKs = collect(\$existingFKs)->pluck('constraint_name')->toArray();
    } else {
        \$existingFKs = [];
    }

    // Loop through relationships
    foreach (\$relationships as \$rel) {
            \$fkName = "{\$tableName}_{\$rel['foreign_key']}_foreign";

            Schema::table(\$tableName, function (Blueprint \$table) use (\$rel, \$fkName, \$existingFKs) {
                // Drop if exists
                if (in_array(\$fkName, \$existingFKs)) {
                    \$table->dropForeign(\$fkName);
                }

                // Add foreign key
                \$table->foreign(\$rel['foreign_key'])
                      ->references(\$rel['local_key'])
                      ->on(\$rel['table'])
                      ->onDelete(\$rel['onDelete'] ?? 'cascade')
                      ->onUpdate(\$rel['onUpdate'] ?? 'cascade');
            });
    }
}



};




PHP;





            // Save to file
            File::put($fileName, $migrationContent);
        }

        Artisan::call('migrate', [
            '--force' => true 
        ]);

        Artisan::call('erd:generate');

    }

    private function parseFullSchema($sql)
    {
        $tables = [];
        $currentTable = null;

        // Split statements by semicolon
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            // Detect CREATE TABLE
            if (preg_match('/CREATE TABLE (\w+)/i', $statement, $matches)) {
                $currentTable = $matches[1];
                $tables[$currentTable] = [
                    'columns' => [],
                    'keys' => [],          // <-- new section for keys
                    'relationships' => [],
                    'table_name' => $currentTable
                ];

                // Match column definitions
                if (preg_match_all('/^\s*(\w+)\s+([a-zA-Z0-9()]+)(.*?)$/im', $statement, $cols, PREG_SET_ORDER)) {
                    foreach ($cols as $col) {
                        $colName = $col[1];
                        $colType = $col[2];
                        $rest = strtolower($col[3]);

                        // Check if it's a key or constraint
                        if (preg_match('/^(primary|unique|key|constraint)$/i', $colName)) {
                            $tables[$currentTable]['keys'][] = [
                                'name' => $colName,
                                'type' => $colType,
                                'definition' => trim($col[3])
                            ];
                            continue;
                        }

                        // Otherwise, it's a normal column
                        $tables[$currentTable]['columns'][] = [
                            'name' => $colName,
                            'type' => $colType,
                            'nullable' => strpos($rest, 'not null') === false,
                            'auto_increment' => strpos($rest, 'auto_increment') !== false,
                            'default' => preg_match('/default\s+([^\s]+)/i', $rest, $d) ? $d[1] : null,
                        ];
                    }
                }
            }

            // Detect ALTER TABLE (relationships)
            if (preg_match('/ALTER TABLE (\w+)/i', $statement, $matches)) {
                $tableName = $matches[1];
                if (preg_match('/FOREIGN KEY\s*\((.*?)\)\s*REFERENCES\s*(\w+)\s*\((.*?)\)/i', $statement, $fk)) {
                    $localKey = trim($fk[1]);
                    $refTable = trim($fk[2]);
                    $refKey   = trim($fk[3]);

                    if (!empty($localKey)) {
                        $tables[$tableName]['relationships'][] = [
                            'type' => 'belongsTo',
                            'table' => $refTable,
                            'local_key' => $localKey,
                            'foreign_key' => $refKey
                        ];
                    }
                }
            }
        }

        return $tables;
    }
}
