# PhpMyMigration

A Laravel package that generates migration files and Eloquent models from existing database tables. This package is compatible with Laravel 9, 10, and 11.

## Features

- Generate migration files from existing database tables
- Automatically detect and include:
  - Column types and properties
  - Primary keys
  - Foreign key relationships
  - Indexes and unique constraints
  - Timestamps and soft deletes
- Generate Eloquent models with:
  - Proper relationships (belongsTo, hasMany, belongsToMany)
  - PHPDoc comments for properties
  - Type casting
  - Soft deletes support
  - Fillable fields
- Support for large databases with batch processing
- Custom output paths for migrations and models
- Force overwrite option for existing files

## Requirements

- PHP 8.1 or higher
- Laravel 9.x, 10.x, or 11.x
- MySQL database

## Installation

You can install the package via Composer:

```bash
composer require adly-nady/php-my-migration
```

The package will automatically register its service provider.

## Usage

### Basic Usage

To generate migration files for all tables in your database:

```bash
php artisan phpmymigration:generate
```

### Generate Both Migrations and Models

To generate both migration files and Eloquent models:

```bash
php artisan phpmymigration:generate --with-models
```

### Generate Only Migrations

To generate only migration files (default behavior):

```bash
php artisan phpmymigration:generate --only-migrations
```

### Specify Database Connection

To use a specific database connection:

```bash
php artisan phpmymigration:generate --connection=mysql
```

### Force Overwrite

To overwrite existing migration/model files:

```bash
php artisan phpmymigration:generate --force
```

### Custom Output Path

To specify custom paths for migrations and models:

```bash
php artisan phpmymigration:generate --path=/custom/path
```

This will create:
- `/custom/path/migrations/` for migration files
- `/custom/path/Models/` for model files

## Examples

### Generated Migration Example

For a `users` table with relationships:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
```

### Generated Model Example

For the same `users` table:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * User Model
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \DateTime|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime|null $deleted_at
 */
class User extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
```

## Contributing

Please feel free to submit any issues or pull requests.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 