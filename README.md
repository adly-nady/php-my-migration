# 🔄 PhpMyMigration

<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20%7C%2010.x%20%7C%2011.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

A powerful Laravel package that automatically generates migration files and Eloquent models from your existing database tables. Perfect for legacy projects or when you need to version control your database structure.

[Installation](#installation) • [Usage](#usage) • [Examples](#examples) • [Features](#features)

</div>

## 🌟 Features

- ✨ **Smart Migration Generation**
  - Automatically detects column types and properties
  - Handles primary keys (single and composite)
  - Supports foreign key relationships
  - Manages indexes and unique constraints
  - Includes timestamps and soft deletes

- 🎯 **Eloquent Model Generation**
  - Creates models with proper relationships
  - Adds comprehensive PHPDoc comments
  - Implements type casting
  - Supports soft deletes
  - Manages fillable fields

- 🚀 **Performance & Flexibility**
  - Batch processing for large databases
  - Custom output paths
  - Force overwrite option
  - Multiple database connection support

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 9.x, 10.x, or 11.x
- MySQL database

## 🚀 Installation

Install the package via Composer:

```bash
composer require adly-nady/php-my-migration
```

The package will automatically register its service provider.

## 💡 Usage

### Basic Usage

Generate migration files for all tables:

```bash
php artisan phpmymigration:generate
```

### Generate Migrations and Models

Generate both migrations and Eloquent models:

```bash
php artisan phpmymigration:generate --with-models
```

### Generate Only Migrations

Generate only migration files (default behavior):

```bash
php artisan phpmymigration:generate --only-migrations
```

### Specify Database Connection

Use a specific database connection:

```bash
php artisan phpmymigration:generate --connection=mysql
```

### Force Overwrite

Overwrite existing migration/model files:

```bash
php artisan phpmymigration:generate --force
```

### Custom Output Path

Specify custom paths for migrations and models:

```bash
php artisan phpmymigration:generate --path=/custom/path
```

This will create:
- `/custom/path/migrations/` for migration files
- `/custom/path/Models/` for model files

## 📝 Examples

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

## 👨‍💻 About Me

Hi! I'm Adly Nady, a passionate Laravel developer. I love creating tools that make developers' lives easier. This package is one of my contributions to the Laravel ecosystem.

### Connect With Me

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/adly-nady-10741b236)
[![Facebook](https://img.shields.io/badge/Facebook-1877F2?style=for-the-badge&logo=facebook&logoColor=white)](https://www.facebook.com/adly.nady.37)
[![GitHub](https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white)](https://github.com/adly-nady)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

---

<div align="center">
Made with ❤️ by Adly Nady
</div> 