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
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Response;
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
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT'])) {
            return $next($request);
        }

        $parsedBody = $request->json()
            ->all();

        if (!$parsedBody) {
            return response()->json([
                'error' => 'Request body can\'t be empty'
            ], 400);
        }

        $openapi = $this->getOpenApi($request);

        if ($openapi && $openapi->requestBody != Generator::UNDEFINED) {
            $mediaType = $request->header('content-type');
            $jsonSchema = json_decode($openapi->requestBody->toJson(), true);
            $jsonSchema = $this->getRef($jsonSchema);
            $jsonSchema = $jsonSchema['content'][$mediaType]['schema'] ?? null;

            $this->validate($parsedBody, $jsonSchema);
        }

        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        if ($openapi && $openapi->responses != Generator::UNDEFINED) {
            $mediaType = $response->headers->get('content-type');

            /** @var Response $jsonSchema */
            $jsonSchema = array_values(array_filter($openapi->responses, function (Response $r) use ($response, $mediaType) {
                    return $r->response == $response->getStatusCode() && isset($r->content[$mediaType]);
                }))[0] ?? null;

            if ($jsonSchema) {
                $jsonSchema = json_decode($jsonSchema->toJson(), true);
                $jsonSchema = $jsonSchema['content'][$mediaType]['schema'] ?? null;

                $this->validate($response->getOriginalContent(), $jsonSchema);
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
     * @param $payload
     * @param $jsonSchema
     * @return bool|\Illuminate\Http\JsonResponse
     */
    protected function validate($payload, $jsonSchema)
    {
        if (!$jsonSchema) {
            throw new InvalidSchemaException();
        }

        if ([] === $payload) {
            $payload = new stdClass();
        } else {
            $payload = json_encode($payload);
            $payload = (object) json_decode($payload);
        }

        $validator = new Validator();
        $validator->validate($payload, $jsonSchema);

        if (!$validator->isValid()) {
            return response()->json([
                'message' => 'Not valid response',
                'errors' => array_map(function ($error) {
                    return [
                        'property' => $error['property'],
                        'message' => $error['message'],
                        'constraint' => $error['constraint'],
                    ];
                }, $validator->getErrors()),
            ], 400);
        }

        return true;
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
