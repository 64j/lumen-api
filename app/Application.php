<?php

declare(strict_types=1);

namespace App;

use Laravel\Lumen\Application as LumenApplication;

class Application extends LumenApplication
{
    /**
     * @var array
     */
    protected array $bootedCallbacks = [];

    /**
     * @var array
     */
    protected array $bootingCallbacks = [];

    /**
     * @param $basePath
     */
    public function __construct($basePath = null)
    {
        parent::__construct($basePath);

        $this->instance('path.storage', $this->storagePath());
    }

    /**
     * Boots the registered providers.
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->fireAppCallbacks($this->bootingCallbacks);

        foreach ($this->loadedProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }

    /**
     * Register a new "booted" listener.
     *
     * @param callable $callback
     * @return void
     */
    public function booted(callable $callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $this->fireAppCallbacks([$callback]);
        }
    }

    /**
     * Register a new boot listener.
     *
     * @param callable $callback
     * @return void
     */
    public function booting(callable $callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param callable[] $callbacks
     * @return void
     */
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Determine if the application routes are cached.
     *
     * @return bool
     */
    public function routesAreCached(): bool
    {
        return $this['files']->exists($this->getCachedRoutesPath());
    }

    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */
    public function getCachedRoutesPath(): string
    {
        //return $this->storagePath('framework/routes.scanned.php' . ($this['config']->get('app.debug') ? '.dev' : ''));
        return $this->storagePath('framework/routes.scanned.php');
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path Optionally, a path to append to the bootstrap path
     * @return string
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Register the core container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases()
    {
        $this->aliases = [
            \Illuminate\Contracts\Foundation\Application::class => 'app',
            \Illuminate\Contracts\Auth\Factory::class => 'auth',
            \Illuminate\Contracts\Auth\Guard::class => 'auth.driver',
            \Illuminate\Contracts\Cache\Factory::class => 'cache',
            \Illuminate\Contracts\Cache\Repository::class => 'cache.store',
            \Illuminate\Contracts\Config\Repository::class => 'config',
            \Illuminate\Config\Repository::class => 'config',
            \Illuminate\Container\Container::class => 'app',
            \Illuminate\Contracts\Container\Container::class => 'app',
            \Illuminate\Database\ConnectionResolverInterface::class => 'db',
            \Illuminate\Database\DatabaseManager::class => 'db',
            \Illuminate\Contracts\Encryption\Encrypter::class => 'encrypter',
            \Illuminate\Contracts\Events\Dispatcher::class => 'events',
            \Illuminate\Contracts\Filesystem\Factory::class => 'filesystem',
            \Illuminate\Contracts\Filesystem\Filesystem::class => 'filesystem.disk',
            \Illuminate\Contracts\Filesystem\Cloud::class => 'filesystem.cloud',
            \Illuminate\Contracts\Hashing\Hasher::class => 'hash',
            'log' => \Psr\Log\LoggerInterface::class,
            \Illuminate\Contracts\Queue\Factory::class => 'queue',
            \Illuminate\Contracts\Queue\Queue::class => 'queue.connection',
            \Illuminate\Redis\RedisManager::class => 'redis',
            \Illuminate\Contracts\Redis\Factory::class => 'redis',
            \Illuminate\Redis\Connections\Connection::class => 'redis.connection',
            \Illuminate\Contracts\Redis\Connection::class => 'redis.connection',
            'request' => \Illuminate\Http\Request::class,
            \Laravel\Lumen\Routing\Router::class => 'router',
            \Illuminate\Contracts\Routing\Registrar::class => 'router',
            \Illuminate\Contracts\Routing\BindingRegistrar::class => 'router',
            \Illuminate\Contracts\Translation\Translator::class => 'translator',
            \Laravel\Lumen\Routing\UrlGenerator::class => 'url',
            \Illuminate\Contracts\Validation\Factory::class => 'validator',
            \Illuminate\Contracts\View\Factory::class => 'view',
        ];
    }
}
