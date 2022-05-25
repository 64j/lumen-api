<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\OpenApiController;
use Closure;
use Illuminate\Http\Request;
use JsonSchema\Validator;
use OpenApi\Annotations\OpenApi;

class RequestBodyMiddlewareBak
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     * @throws \ReflectionException
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $parsedBody = json_decode($request->getContent(), true);

        if (!$parsedBody) {
            $message = 'Request body can\'t be empty';
            if (is_null($parsedBody)) {
                abort(400, $message);
            } else {
                return response()->json([
                    'error' => $message
                ], 400);
            }
        }

        $jsonSchema = app(OpenApiController::class)->index();
        $path = $request->getPathInfo();
        $method = strtolower($request->getMethod());
        $mediaType = $request->header('content-type');
        $openApi = new OpenApi($jsonSchema);

        $openApi->paths = array_filter($openApi->paths, function ($value, $key) use ($path, $method) {
            return $key == $path && isset($value[$method]);
        }, ARRAY_FILTER_USE_BOTH);

        if ($openApi->paths) {
            $requestBody = $openApi->paths[$path][$method]['requestBody'] ?? [];
            //$jsonSchema = $requestBody;
            $jsonSchema = $this->getRef($requestBody, $openApi->components['schemas'] ?? []);
            $jsonSchema = $jsonSchema['content'][$mediaType]['schema'] ?? [];

            if ($jsonSchema) {
                $payload = $parsedBody;

                switch ($jsonSchema['type']) {
                    case 'array':
                        if ([] === $parsedBody) {
                            $payload = [];
                        } else {
                            $payload = json_encode($parsedBody);
                            $payload = (array) json_decode($payload);
                        }
                        break;

                    case 'object':
                        if ([] === $parsedBody) {
                            $payload = new \stdClass();
                        } else {
                            $payload = json_encode($parsedBody);
                            $payload = (object) json_decode($payload);
                        }
                        break;

                    case 'string':
                        $payload = (string) $request->getContent();
                        break;
                }

//                $schemaStorage = new SchemaStorage();
//                //$schemaStorage->addSchema('', $openApi->components['schemas']);
//                foreach ($openApi->components['schemas'] as $name => $schema) {
//                    $schemaStorage->addSchema($name, $schema);
//                }
//
//                $validator = new Validator(new Factory($schemaStorage));
                //dd($schemaStorage->resolveRefSchema($jsonSchema));

//                dd($validator->validate($payload, $jsonSchema));
                $validator = new Validator();
                $validator->validate($payload, $jsonSchema);

                if (!$validator->isValid()) {
                    return response()->json([
                        'errors' => array_map(function ($error) {
                            return [
                                'property' => $error['property'],
                                'message' => $error['message'],
                                'constraint' => $error['constraint'],
                            ];
                        }, $validator->getErrors()),
                        'payload' => $payload,
                    ], 400);
                }
            }
        }

        return $next($request);
    }

    /**
     * @param array $data
     * @param array $components
     * @return array|false|string
     */
    protected function getRef(array $data = [], array $components = [])
    {
        foreach ($data as $key => &$item) {
            if (is_array($item)) {
                $item = $this->getRef($item, $components);
            } elseif ($key == '$ref') {
                if (substr($item, 0, 2) === '#/') {
                    $name = basename($item);
                    if (isset($components[$name])) {
                        $data = $components[$name];
                        break;
                    }
                } else {
                    $path = base_path('public') . $item;
                    if (file_exists($path)) {
                        $data = json_decode(file_get_contents($path), true);
                        break;
                    }
                }
            }
        }

        return $data;
    }
}
