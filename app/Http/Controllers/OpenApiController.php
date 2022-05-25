<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use OpenApi\Analysis;
use OpenApi\Annotations\OpenApi;
use OpenApi\Context;
use OpenApi\Generator;

class OpenApiController extends Controller
{
    /**
     * @return array
     */
    public function index(): array
    {
        $ttl = env('APP_DEBUG') ? 0 : 1440;

        return Cache::remember('openapi', $ttl, function () {
            app()->configure('openapi');

            $config = config('openapi', [
                'info' => [
                    'version' => env('APP_VERSION'),
                    'title' => env('APP_NAME'),
                    'description' => 'OpenApi for app',
                ],
                'paths' => [],
            ]);

            $analysis = new Analysis([new OpenApi($config)], new Context());

            $openapi = Generator::scan([base_path('app')], [
                'analysis' => $analysis,
                'validate' => true
            ]);

            return json_decode($openapi->toJson(), true);
        });
    }
}
