<?php

namespace AdlyNady\PhpMyMigration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class GenerateFromDatabase extends Command
{
    protected $signature = 'phpmymigration:generate 
                          {--only-migrations : Generate only migration files}
                          {--with-models : Generate both migration files and model classes}
                          {--connection= : The database connection to use}
                          {--force : Force overwrite of existing files}
                          {--path= : Custom output path for migrations and models}
                          {--exclude= : Comma-separated list of tables to exclude}
                          {--include= : Comma-separated list of tables to include only}
                          {--dry-run : Show what would be generated without actually creating files}';

    protected $description = 'Generate migrations and models from existing database tables with relationships';

    protected $batchSize = 50;
    protected $migrationsPath;
    protected $modelsPath;
    protected $processedTables = 0;
    protected $totalTables = 0;
    protected $table;
    protected $excludedTables = [];
    protected $includedTables = [];
    protected $dryRun = false;

    public function handle()
    {
        try {
            $this->setupOptions();
            $this->setPaths($this->option('path'));
            
            $tables = $this->getAllTables($this->option('connection'));
            
            if (empty($tables)) {
                $this->error('No tables found in the database.');
                return 1;
            }

            $this->totalTables = count($tables);
            $this->info("Found {$this->totalTables} tables to process.");

            foreach (array_chunk($tables, $this->batchSize) as $batch) {
                $this->processBatch($batch, $this->option('connection'), $this->option('only-migrations'), $this->option('with-models'), $this->option('force'));
                $this->processedTables += count($batch);
                $this->info("Processed {$this->processedTables}/{$this->totalTables} tables.");
            }

            if (!$this->dryRun) {
                $this->info('Generation completed successfully!');
            } else {
                $this->info('Dry run completed. No files were created.');
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error('PhpMyMigration Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'connection' => $this->option('connection')
            ]);
            
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    protected function setupOptions()
    {
        $this->dryRun = $this->option('dry-run');
        
        if ($exclude = $this->option('exclude')) {
            $this->excludedTables = array_map('trim', explode(',', $exclude));
        }
        
        if ($include = $this->option('include')) {
            $this->includedTables = array_map('trim', explode(',', $include));
        }
    }

    protected function shouldProcessTable($tableName)
    {
        if (!empty($this->includedTables)) {
            return in_array($tableName, $this->includedTables);
        }
        
        if (!empty($this->excludedTables)) {
            return !in_array($tableName, $this->excludedTables);
        }
        
        return true;
    }

    protected function processBatch($tables, $connection, $onlyMigrations, $withModels, $force)
    {
        $tableInfo = [];
        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            
            if ($tableName === 'migrations' || !$this->shouldProcessTable($tableName)) {
                continue;
            }
            
            try {
                $tableInfo[$tableName] = $this->getTableInfo($tableName, $connection);
            } catch (\Exception $e) {
                Log::warning("Error processing table {$tableName}: " . $e->getMessage());
                $this->warn("Error processing table {$tableName}: " . $e->getMessage());
            }
        }

        foreach ($tableInfo as $tableName => $info) {
            try {
                if (!$this->dryRun) {
                    $this->generateMigration($tableName, $info, $force);
                    
                    if ($withModels && !$onlyMigrations) {
                        $this->generateModel($tableName, $info, $tableInfo, $force);
                    }
                } else {
                    $this->info("Would generate migration for table: {$tableName}");
                    if ($withModels && !$onlyMigrations) {
                        $this->info("Would generate model for table: {$tableName}");
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Error generating files for table {$tableName}: " . $e->getMessage());
                $this->warn("Error generating files for table {$tableName}: " . $e->getMessage());
            }
        }
    }

    protected function getTableInfo($tableName, $connection)
    {
        $info = [
            'table' => $tableName,
            'columns' => DB::connection($connection)->select("SHOW COLUMNS FROM `{$tableName}`"),
            'foreign_keys' => DB::connection($connection)->select("
                SELECT 
                    k.COLUMN_NAME,
                    k.REFERENCED_TABLE_NAME,
                    k.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE k
                JOIN information_schema.REFERENTIAL_CONSTRAINTS rc 
                    ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND k.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
                WHERE k.TABLE_SCHEMA = DATABASE()
                AND k.TABLE_NAME = '{$tableName}'
                AND k.REFERENCED_TABLE_NAME IS NOT NULL
            "),
            'indexes' => DB::connection($connection)->select("SHOW INDEX FROM `{$tableName}`"),
            'primary_keys' => DB::connection($connection)->select("
                SELECT COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tableName}'
                AND CONSTRAINT_NAME = 'PRIMARY'
            "),
        ];

        return $info;
    }

    protected function setPaths($customPath)
    {
        if ($customPath) {
            $this->migrationsPath = $customPath . '/migrations';
            $this->modelsPath = $customPath . '/Models';
        } else {
            $this->migrationsPath = rtrim(App::basePath(), '/app') . '/database/migrations';
            $this->modelsPath = App::basePath('app/Models');
        }

        if (!File::exists($this->migrationsPath)) {
            File::makeDirectory($this->migrationsPath, 0755, true);
        }
        if (!File::exists($this->modelsPath)) {
            File::makeDirectory($this->modelsPath, 0755, true);
        }
    }

    protected function getAllTables($connection)
    {
        if (App::version() >= '10.0.0') {
            return Schema::getAllTables();
        }

        return DB::connection($connection)->select('SHOW TABLES');
    }

    protected function generateMigration($tableName, $info, $force)
    {
        $migrationContent = $this->generateMigrationContent($tableName, $info);
        
        $timestamp = date('Y_m_d_His');
        $migrationName = "{$timestamp}_create_{$tableName}_table.php";
        $migrationPath = "{$this->migrationsPath}/{$migrationName}";
        
        if (File::exists($migrationPath) && !$force) {
            $this->warn("Migration already exists for table: {$tableName}");
            return;
        }

        File::put($migrationPath, $migrationContent);
        $this->info("Created migration: {$migrationName}");
    }

    protected function generateModel($tableName, $info, $allTables, $force)
    {
        $modelContent = $this->generateModelContent($tableName, $info, $allTables);
        
        $modelName = Str::studly(Str::singular($tableName));
        $modelPath = "{$this->modelsPath}/{$modelName}.php";
        
        if (File::exists($modelPath) && !$force) {
            $this->warn("Model already exists for table: {$tableName}");
            return;
        }

        File::put($modelPath, $modelContent);
        $this->info("Created model: {$modelName}");
    }

    protected function generateMigrationContent($tableName, $info)
    {
        $className = 'Create' . Str::studly($tableName) . 'Table';
        
        $content = "<?php\n\n";
        $content .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $content .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $content .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $content .= "return new class extends Migration\n";
        $content .= "{\n";
        $content .= "    public function up()\n";
        $content .= "    {\n";
        $content .= "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";
        
        foreach ($info['columns'] as $column) {
            $column = (array) $column;
            $name = $column['Field'];
            $type = $this->mapColumnType($column['Type']);
            $nullable = $column['Null'] === 'YES';
            $default = $column['Default'];
            
            if ($name === 'created_at' && $this->hasColumn($info['columns'], 'updated_at')) {
                $content .= "            \$table->timestamps();\n";
                continue;
            }
            if ($name === 'deleted_at') {
                $content .= "            \$table->softDeletes();\n";
                continue;
            }
            
            if ($this->isPrimaryKey($info['primary_keys'], $name)) {
                if ($name === 'id' && $type === 'integer') {
                    $content .= "            \$table->id();\n";
                } else {
                    $content .= "            \$table->{$type}('{$name}')->primary();\n";
                }
                continue;
            }
            
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
        
        foreach ($info['foreign_keys'] as $fk) {
            $fk = (array) $fk;
            $content .= "            \$table->foreign('{$fk['COLUMN_NAME']}')\n";
            $content .= "                  ->references('{$fk['REFERENCED_COLUMN_NAME']}')\n";
            $content .= "                  ->on('{$fk['REFERENCED_TABLE_NAME']}')\n";
            
            if ($fk['UPDATE_RULE'] !== 'RESTRICT') {
                $content .= "                  ->onUpdate('{$fk['UPDATE_RULE']}')\n";
            }
            if ($fk['DELETE_RULE'] !== 'RESTRICT') {
                $content .= "                  ->onDelete('{$fk['DELETE_RULE']}')\n";
            }
            
            $content .= "                  ;\n";
        }
        
        $indexes = [];
        foreach ($info['indexes'] as $index) {
            $index = (array) $index;
            if ($index['Key_name'] === 'PRIMARY') continue;
            
            $indexes[$index['Key_name']][] = $index['Column_name'];
        }
        
        foreach ($indexes as $name => $columns) {
            $unique = $this->isUniqueIndex($info['indexes'], $name);
            $method = $unique ? 'unique' : 'index';
            
            $content .= "            \$table->{$method}(['" . implode("', '", $columns) . "'], '{$name}');\n";
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

    protected function hasColumn($columns, $name)
    {
        foreach ($columns as $column) {
            $column = (array) $column;
            if ($column['Field'] === $name) {
                return true;
            }
        }
        return false;
    }

    protected function isPrimaryKey($primaryKeys, $columnName)
    {
        foreach ($primaryKeys as $pk) {
            $pk = (array) $pk;
            if ($pk['COLUMN_NAME'] === $columnName) {
                return true;
            }
        }
        return false;
    }

    protected function generateModelContent($tableName, $info, $allTables)
    {
        try {
            $this->table = $tableName;
            $modelName = Str::studly(Str::singular($tableName));
            $hasSoftDeletes = false;
            $fillable = [];
            $casts = [];
            $relationships = [];
            
            foreach ($info['columns'] as $column) {
                $column = (array) $column;
                $name = $column['Field'] ?? '';
                
                if (empty($name) || in_array($name, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }
                
                if ($name === 'deleted_at') {
                    $hasSoftDeletes = true;
                    continue;
                }
                
                if (!$this->isForeignKey($info['foreign_keys'], $name)) {
                    $fillable[] = $name;
                }
                
                $type = $this->mapColumnType($column['Type']);
                if ($this->shouldCast($type)) {
                    $casts[$name] = $this->mapCastType($type);
                }
            }
            
            foreach ($info['foreign_keys'] as $fk) {
                $fk = (array) $fk;
                if (empty($fk['REFERENCED_TABLE_NAME']) || empty($fk['COLUMN_NAME']) || 
                    empty($fk['REFERENCED_COLUMN_NAME'])) {
                    continue;
                }
                
                $relatedModel = Str::studly(Str::singular($fk['REFERENCED_TABLE_NAME']));
                $localKey = $fk['COLUMN_NAME'];
                
                if (empty($relatedModel) || empty($localKey)) {
                    continue;
                }
                
                $relationships[] = [
                    'type' => 'belongsTo',
                    'model' => $relatedModel,
                    'foreignKey' => $localKey,
                    'ownerKey' => $fk['REFERENCED_COLUMN_NAME'],
                ];
            }
            
            foreach ($allTables as $otherTable => $otherInfo) {
                if ($otherTable === $tableName) continue;
                
                foreach ($otherInfo['foreign_keys'] as $fk) {
                    $fk = (array) $fk;
                    if (empty($fk['REFERENCED_TABLE_NAME']) || empty($fk['COLUMN_NAME']) || 
                        empty($fk['REFERENCED_COLUMN_NAME']) || $fk['REFERENCED_TABLE_NAME'] !== $tableName) {
                        continue;
                    }
                    
                    $relatedModel = Str::studly(Str::singular($otherTable));
                    $foreignKey = $fk['COLUMN_NAME'];
                    
                    if (empty($relatedModel) || empty($foreignKey)) {
                        continue;
                    }
                    
                    $relationships[] = [
                        'type' => 'hasMany',
                        'model' => $relatedModel,
                        'foreignKey' => $foreignKey,
                        'localKey' => $fk['REFERENCED_COLUMN_NAME'],
                    ];
                }
            }
            
            if ($this->isPivotTable($info)) {
                $pivotRelations = $this->getPivotRelations($info, $allTables);
                $relationships = array_merge($relationships, $pivotRelations);
            }
            
            $content = "<?php\n\n";
            $content .= "namespace App\\Models;\n\n";
            $content .= "use Illuminate\\Database\\Eloquent\\Model;\n";
            if ($hasSoftDeletes) {
                $content .= "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            }
            $content .= "\n";
            
            $content .= "/**\n";
            $content .= " * {$modelName} Model\n";
            $content .= " *\n";
            foreach ($info['columns'] as $column) {
                $column = (array) $column;
                $type = $this->mapPhpDocType($column['Type']);
                $content .= " * @property {$type} \${$column['Field']}\n";
            }
            $content .= " */\n";
            
            $content .= "class {$modelName} extends Model\n";
            $content .= "{\n";
            
            if ($hasSoftDeletes) {
                $content .= "    use SoftDeletes;\n\n";
            }
            
            $content .= "    protected \$table = '{$tableName}';\n\n";
            
            if (!empty($fillable)) {
                $content .= "    protected \$fillable = [\n";
                foreach ($fillable as $field) {
                    $content .= "        '{$field}',\n";
                }
                $content .= "    ];\n\n";
            }
            
            if (!empty($casts)) {
                $content .= "    protected \$casts = [\n";
                foreach ($casts as $field => $type) {
                    $content .= "        '{$field}' => '{$type}',\n";
                }
                $content .= "    ];\n\n";
            }
            
            foreach ($relationships as $relation) {
                $content .= $this->generateRelationshipMethod($relation);
            }
            
            $content .= "}\n";
            
            return $content;
        } catch (\Exception $e) {
            Log::warning("Error generating model for table {$tableName}: " . $e->getMessage());
            return '';
        }
    }

    protected function isForeignKey($foreignKeys, $columnName)
    {
        foreach ($foreignKeys as $fk) {
            $fk = (array) $fk;
            if ($fk['COLUMN_NAME'] === $columnName) {
                return true;
            }
        }
        return false;
    }

    protected function isUniqueIndex($indexes, $indexName)
    {
        foreach ($indexes as $index) {
            $index = (array) $index;
            if ($index['Key_name'] === $indexName && $index['Non_unique'] == 0) {
                return true;
            }
        }
        return false;
    }

    protected function isPivotTable($info)
    {
        $foreignKeys = count($info['foreign_keys']);
        $columns = count($info['columns']);
        
        // A pivot table typically has:
        // - Two foreign keys
        // - Minimal additional columns (usually just timestamps)
        return $foreignKeys === 2 && $columns <= 4;
    }

    protected function getPivotRelations($info, $allTables)
    {
        try {
            $relations = [];
            $foreignKeys = $info['foreign_keys'] ?? [];
            
            if (count($foreignKeys) === 2) {
                $fk1 = (array) $foreignKeys[0];
                $fk2 = (array) $foreignKeys[1];
                
                if (empty($fk1['REFERENCED_TABLE_NAME']) || empty($fk2['REFERENCED_TABLE_NAME']) ||
                    empty($fk1['COLUMN_NAME']) || empty($fk2['COLUMN_NAME'])) {
                    return $relations;
                }
                
                $table1 = $fk1['REFERENCED_TABLE_NAME'];
                $table2 = $fk2['REFERENCED_TABLE_NAME'];
                
                if (empty($table1) || empty($table2)) {
                    return $relations;
                }
                
                $model1 = Str::studly(Str::singular($table1));
                $model2 = Str::studly(Str::singular($table2));
                
                if (empty($model1) || empty($model2)) {
                    return $relations;
                }
                
                $relations[] = [
                    'type' => 'belongsToMany',
                    'model' => $model1,
                    'table' => $info['table'],
                    'foreignKey' => $fk1['COLUMN_NAME'],
                    'relatedKey' => $fk2['COLUMN_NAME'],
                    'relationName' => Str::camel(Str::plural($table2)),
                ];
                
                $relations[] = [
                    'type' => 'belongsToMany',
                    'model' => $model2,
                    'table' => $info['table'],
                    'foreignKey' => $fk2['COLUMN_NAME'],
                    'relatedKey' => $fk1['COLUMN_NAME'],
                    'relationName' => Str::camel(Str::plural($table1)),
                ];
            }
            
            return $relations;
        } catch (\Exception $e) {
            Log::warning("Error generating pivot relations: " . $e->getMessage());
            return [];
        }
    }

    protected function generateRelationshipMethod($relation)
    {
        try {
            if (empty($relation['model']) || empty($relation['type'])) {
                return '';
            }

            $modelName = $relation['model'];
            $tableName = $this->table ?? '';
            
            if (empty($modelName) || empty($tableName)) {
                return '';
            }

            $content = "    /**\n";
            $content .= "     * Get the " . Str::singular($modelName) . " that owns this " . Str::singular($tableName) . "\n";
            $content .= "     */\n";
            
            switch ($relation['type']) {
                case 'belongsTo':
                    if (empty($relation['foreignKey']) || empty($relation['ownerKey'])) {
                        return '';
                    }
                    $content .= "    public function " . Str::camel(Str::singular($modelName)) . "()\n";
                    $content .= "    {\n";
                    $content .= "        return \$this->belongsTo({$modelName}::class, '{$relation['foreignKey']}', '{$relation['ownerKey']}');\n";
                    $content .= "    }\n\n";
                    break;
                    
                case 'hasMany':
                    if (empty($relation['foreignKey']) || empty($relation['localKey'])) {
                        return '';
                    }
                    $content .= "    public function " . Str::camel(Str::plural($modelName)) . "()\n";
                    $content .= "    {\n";
                    $content .= "        return \$this->hasMany({$modelName}::class, '{$relation['foreignKey']}', '{$relation['localKey']}');\n";
                    $content .= "    }\n\n";
                    break;
                    
                case 'belongsToMany':
                    if (empty($relation['relationName']) || empty($relation['table']) || 
                        empty($relation['foreignKey']) || empty($relation['relatedKey'])) {
                        return '';
                    }
                    $content .= "    public function {$relation['relationName']}()\n";
                    $content .= "    {\n";
                    $content .= "        return \$this->belongsToMany({$modelName}::class, '{$relation['table']}', '{$relation['foreignKey']}', '{$relation['relatedKey']}');\n";
                    $content .= "    }\n\n";
                    break;
            }
            
            return $content;
        } catch (\Exception $e) {
            Log::warning("Error generating relationship method: " . $e->getMessage());
            return '';
        }
    }

    protected function mapPhpDocType($type)
    {
        $type = strtolower($type);
        
        if (str_contains($type, 'int')) {
            return 'int';
        }
        if (str_contains($type, 'varchar') || str_contains($type, 'char')) {
            return 'string';
        }
        if (str_contains($type, 'text')) {
            return 'string';
        }
        if (str_contains($type, 'datetime')) {
            return '\\DateTime';
        }
        if (str_contains($type, 'timestamp')) {
            return '\\DateTime';
        }
        if (str_contains($type, 'decimal')) {
            return 'float';
        }
        if (str_contains($type, 'float')) {
            return 'float';
        }
        if (str_contains($type, 'boolean')) {
            return 'bool';
        }
        if (str_contains($type, 'json')) {
            return 'array';
        }
        
        return 'mixed';
    }

    protected function mapColumnType($type)
    {
        $type = strtolower($type);
        
        if (str_contains($type, 'int')) {
            return 'integer';
        }
        if (str_contains($type, 'varchar') || str_contains($type, 'char')) {
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

    protected function shouldCast($type)
    {
        return in_array($type, [
            'integer',
            'float',
            'decimal',
            'boolean',
            'json',
            'dateTime',
            'timestamp',
        ]);
    }

    protected function mapCastType($type)
    {
        switch ($type) {
            case 'integer':
                return 'int';
            case 'float':
            case 'decimal':
                return 'float';
            case 'boolean':
                return 'bool';
            case 'json':
                return 'array';
            case 'dateTime':
            case 'timestamp':
                return 'datetime';
            default:
                return 'string';
        }
    }
} 