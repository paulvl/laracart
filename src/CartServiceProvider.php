<?php

namespace LaraCart;

use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laracart.php' => config_path('laracart.php')
        ]);        
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFaztorCartInstance();
    }

    /**
     * Register the laracart cart instance.
     *
     * @return void
     */
    protected function registerFaztorCartInstance()
    {
        $this->app->singleton('laracart.cart', function ($app) {
            return new Cart();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['laracart.cart'];
    }
}
