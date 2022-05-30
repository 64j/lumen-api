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
use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Response;
use OpenApi\Context;
use OpenApi\Generator;
use ReflectionMethod;

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
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT'])) {
            return $next($request);
        }

        $openapi = $this->getOpenApi($request);

        if ($openapi) {
            $schema = $this->getSchema($openapi->requestBody, $request->header('content-type'));
            if (!$this->validate($request->getContent(), $schema, 'Not valid request')) {
                return null;
            }
        }

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        if ($openapi) {
            $mediaType = $response->headers->get('content-type');
            /** @var Response $jsonSchema */
            $jsonSchema = array_values(array_filter($openapi->responses, function (Response $r) use ($response, $mediaType) {
                    return $r->response == $response->getStatusCode() && isset($r->content[$mediaType]);
                }))[0] ?? null;

            $schema = $this->getSchema($jsonSchema, $response->headers->get('content-type'));
            if (!$this->validate($response->getContent(), $schema, 'Not valid response')) {
                return null;
            }
        }

        return $response;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \OpenApi\Annotations\Operation|null
     * @throws \ReflectionException
     */
    protected function getOpenApi(Request $request): ?Operation
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

            return $analysis->openapi->paths[0]->{strtolower($request->getMethod())} ?? null;
        }

        return null;
    }

    /**
     * @param string $content
     * @param string $type
     * @return array|object|\stdClass|string
     */
    protected function getContent(string $content, string $type)
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
                    $content = new \stdClass();
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
     * @param \OpenApi\Annotations\AbstractAnnotation $schema
     * @param string $type
     * @return array
     */
    protected function getSchema(AbstractAnnotation $schema, string $type): array
    {
        $json = $schema->toJson();
        $data = $json ? $this->getRef(json_decode($json, true)) : [];

        return $data['content'][$type]['schema'] ?? [];
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
