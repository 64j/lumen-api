<?php

namespace App\Providers;

use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Traits\ForwardsCalls;
use Laravel\Lumen\Routing\Router;

/**
 * @mixin \Illuminate\Routing\Router
 */
class RouteServiceProvider extends ServiceProvider
{
    use ForwardsCalls;

    /**
     * The controller namespace for the application.
     *
     * @var string|null
     */
    protected ?string $namespace = null;

    /**
     * The callback that should be used to load the application's routes.
     *
     * @var \Closure|null
     */
    protected ?Closure $loadRoutesUsing = null;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->setRootControllerNamespace();

        if ($this->routesAreCached()) {
            $this->loadCachedRoutes();
        } else {
            $this->loadRoutes();

//            $this->app->booted(function () {
//                $this->app['router']->getRoutes()
//                    ->refreshNameLookups();
//                $this->app['router']->getRoutes()
//                    ->refreshActionLookups();
//            });
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the callback that will be used to load the application's routes.
     *
     * @param \Closure $routesCallback
     * @return $this
     */
    protected function routes(Closure $routesCallback): RouteServiceProvider
    {
        $this->loadRoutesUsing = $routesCallback;

        return $this;
    }

    /**
     * Set the root controller namespace for the application.
     *
     * @return void
     */
    protected function setRootControllerNamespace()
    {
        if (!is_null($this->namespace)) {
            $this->app[UrlGenerator::class]->setRootControllerNamespace($this->namespace);
        }
    }

    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    protected function routesAreCached(): bool
    {
        return $this->app->routesAreCached();
    }

    /**
     * Load the cached routes for the application.
     *
     * @return void
     */
    protected function loadCachedRoutes()
    {
        $this->app->booted(function ($router) {
            $router = $router->router;
            require $this->app->getCachedRoutesPath();
        });
    }

    /**
     * Load the application routes.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        if (!is_null($this->loadRoutesUsing)) {
            $this->app->call($this->loadRoutesUsing);
        } elseif (method_exists($this, 'map')) {
            $this->app->call([$this, 'map']);
        }
    }

    /**
     * Pass dynamic methods onto the router instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo(
            $this->app->make(Router::class), $method, $parameters
        );
    }
}
