<?php

namespace LadyBird\StreamImport;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;

class ImportServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $this->publishes([
            __DIR__.'/config/import.php' => config_path('import.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__.'/migrations/');

        Builder::macro('whereLike', function ($attributes, string $searchTerm) {
            $this->where(function (Builder $query) use ($attributes, $searchTerm) {
                foreach (array_wrap($attributes) as $attribute) {
                    $query->orWhere($attribute, '=', $searchTerm);
                }
            });

            return $this;
        });
    }
}
