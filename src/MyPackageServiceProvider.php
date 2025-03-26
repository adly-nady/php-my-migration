<?php

namespace AdlyNady\PhpMyMigration;

use Illuminate\Support\ServiceProvider;
use AdlyNady\PhpMyMigration\Console\Commands\GenerateFromDatabase;

class MyPackageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFromDatabase::class,
            ]);
        }
    }

    public function register()
    {
        $this->app->singleton('mypackage', function () {
            return new MyPackage();
        });
    }
}