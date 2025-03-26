<?php

namespace AdlyNady\PhpMyMigration;

use Illuminate\Support\ServiceProvider;
use AdlyNady\PhpMyMigration\Console\Commands\GenerateMigrationsCommand;

class MyPackageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateMigrationsCommand::class,
            ]);
        }
    }

    public function register()
    {
        // هنا بتسجل الخدمات بتاعتك
        $this->app->singleton('mypackage', function () {
            return new MyPackage();
        });
    }
}