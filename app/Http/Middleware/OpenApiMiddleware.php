<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSchema\Exception\InvalidSchemaException;
use JsonSchema\Validator;
use OpenApi\Analysers\DocBlockParser;
use OpenApi\Analysis;
use OpenApi\Context;
use OpenApi\Generator;
use ReflectionMethod;
use stdClass;

class OpenApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     * @throws \ReflectionException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $openapi = $this->getOpenApi($request);

        if (isset($openapi['requestBody']['content'])) {
            $mediaType = $request->header('content-type');

            if ($mediaType) {
                $jsonSchema = $openapi['requestBody']['content'][$mediaType]['schema'] ?? null;

                if ($jsonSchema) {
                    if (!$this->validate($request->getContent(), $jsonSchema, 'Not valid request')) {
                        return null;
                    }
                }
            }
        }


        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        if (isset($openapi['responses'][$response->getStatusCode()]['content'])) {
            $mediaType = $response->headers->get('content-type');

            if ($mediaType) {
                $jsonSchema = $openapi['responses'][$response->getStatusCode()]['content'][$mediaType]['schema'] ?? null;

                if ($jsonSchema) {
                    if (!$this->validate($response->getContent(), $jsonSchema, 'Not valid response')) {
                        return null;
                    }
                }
            }
        }

        return $response;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     * @throws \ReflectionException
     */
    protected function getOpenApi(Request $request): array
    {
        $route = $request->route()[1] ?? null;

        if ($route) {
            [$class, $method] = Str::parseCallback($route['uses']);
            $generator = new Generator();
            $docBlock = (new ReflectionMethod($class, $method))->getDocComment();
            $annotations = $generator->withContext(function (Generator $generator, Analysis $analysis, Context $context) use ($docBlock) {
                $docBlockParser = new DocBlockParser($generator->getAliases());

                return $docBlockParser->fromComment($docBlock, $context);
            });
            $analysis = new Analysis($annotations, new Context());
            $analysis->process($generator->getProcessors());

            $schema = json_decode($analysis->openapi->toJson(), true)['paths'] ?? [];
            $schema = $schema[$request->getPathInfo()][strtolower($request->getMethod())] ?? [];

            return $this->getRef($schema);
        }

        return [];
    }

    /**
     * @param string $content
     * @param string $type
     * @return object|array|string
     */
    protected function getContent(string $content, string $type): object|array|string
    {
        switch ($type) {
            case 'array':
                if ('' === $content) {
                    $content = [];
                } else {
                    $content = (array) json_decode($content);
                }
                break;

            case 'object':
                if ('' === $content) {
                    $content = new stdClass();
                } else {
                    $content = (object) json_decode($content);
                }
                break;

            case 'string':
                break;
        }

        return $content;
    }

    /**
     * @param $payload
     * @param $jsonSchema
     * @param string $message
     * @return bool
     */
    protected function validate($payload, $jsonSchema, string $message = ''): bool
    {
        if (!$jsonSchema) {
            throw new InvalidSchemaException();
        }

        $payload = $this->getContent($payload, $jsonSchema['type']);

        $validator = new Validator();
        $validator->validate($payload, $jsonSchema);

        if (!$validator->isValid()) {
            response()
                ->json([
                    'message' => $message,
                    'errors' => array_map(function ($error) {
                        return [
                            'property' => $error['property'],
                            'message' => $error['message'],
                            'constraint' => $error['constraint'],
                        ];
                    }, $validator->getErrors()),
                ], 400)
                ->send();
        }

        return $validator->isValid();
    }

    /**
     * @param array $data
     * @param array $components
     * @return array
     */
    protected function getRef(array $data = [], array $components = []): array
    {
        foreach ($data as $key => &$item) {
            if (is_array($item)) {
                $item = $this->getRef($item, $components);
            } elseif ($key == '$ref') {
                if (str_starts_with($item, '#/')) {
                    $name = basename($item);
                    if (isset($components[$name])) {
                        $data = $components[$name];
                        break;
                    }
                } else {
                    $path = base_path('public') . '/' . ltrim($item, '/');
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
