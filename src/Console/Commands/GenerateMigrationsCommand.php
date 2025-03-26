<?php

namespace AdlyNady\PhpMyMigration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GenerateMigrationsCommand extends Command
{
    protected $signature = 'MyMigration:start';
    protected $description = 'Generate migrations from existing database tables';

    public function handle()
    {
        $tables = DB::select('SHOW TABLES');
        
        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            
            // Skip migrations table
            if ($tableName === 'migrations') {
                continue;
            }

            $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $migrationContent = $this->generateMigrationContent($tableName, $columns);
            
            $timestamp = date('Y_m_d_His');
            $migrationName = "{$timestamp}_create_{$tableName}_table.php";
            
            $migrationPath = database_path("migrations/{$migrationName}");
            
            if (!file_exists($migrationPath)) {
                file_put_contents($migrationPath, $migrationContent);
                $this->info("Created migration: {$migrationName}");
            } else {
                $this->warn("Migration already exists for table: {$tableName}");
            }
        }
    }

    protected function generateMigrationContent($tableName, $columns)
    {
        $className = 'Create' . Str::studly($tableName) . 'Table';
        
        $content = "<?php\n\n";
        $content .= "use Illuminate\Database\Migrations\Migration;\n";
        $content .= "use Illuminate\Database\Schema\Blueprint;\n";
        $content .= "use Illuminate\Support\Facades\Schema;\n\n";
        $content .= "return new class extends Migration\n";
        $content .= "{\n";
        $content .= "    public function up()\n";
        $content .= "    {\n";
        $content .= "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";
        
        foreach ($columns as $column) {
            $column = (array) $column;
            $name = $column['Field'];
            $type = $this->mapColumnType($column['Type']);
            $nullable = $column['Null'] === 'YES';
            $default = $column['Default'];
            
            $line = "            \$table->{$type}('{$name}')";
            
            if ($nullable) {
                $line .= "->nullable()";
            }
            
            if ($default !== null) {
                $line .= "->default('{$default}')";
            }
            
            $line .= ";\n";
            $content .= $line;
        }
        
        $content .= "        });\n";
        $content .= "    }\n\n";
        $content .= "    public function down()\n";
        $content .= "    {\n";
        $content .= "        Schema::dropIfExists('{$tableName}');\n";
        $content .= "    }\n";
        $content .= "};\n";
        
        return $content;
    }

    protected function mapColumnType($type)
    {
        $type = strtolower($type);
        
        if (str_contains($type, 'int')) {
            return 'integer';
        }
        if (str_contains($type, 'varchar')) {
            return 'string';
        }
        if (str_contains($type, 'text')) {
            return 'text';
        }
        if (str_contains($type, 'datetime')) {
            return 'dateTime';
        }
        if (str_contains($type, 'timestamp')) {
            return 'timestamp';
        }
        if (str_contains($type, 'decimal')) {
            return 'decimal';
        }
        if (str_contains($type, 'float')) {
            return 'float';
        }
        if (str_contains($type, 'boolean')) {
            return 'boolean';
        }
        if (str_contains($type, 'json')) {
            return 'json';
        }
        
        return 'string';
    }
} 