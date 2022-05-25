<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSchema\Validator;
use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Generator;
use ReflectionMethod;

class RequestBodyMiddleware
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

        $route = $request->route()[1] ?? null;

        if ($route) {
            [$class, $method] = Str::parseCallback($route['uses']);

            $reader = new AnnotationReader();
            $openapi = $reader->getMethodAnnotation(new ReflectionMethod($class, $method), AbstractAnnotation::class);

            if ($openapi) {
                if ($openapi->path == Generator::UNDEFINED) {
                    $openapi->path = $request->getPathInfo();
                }

                if ($openapi->requestBody != Generator::UNDEFINED) {
                    $jsonSchema = json_decode($openapi->requestBody->toJson(), true);
                    $jsonSchema = $this->getRef($jsonSchema);

                    dd($jsonSchema);
//                    $mediaType = $request->header('content-type');
//                    $content = array_filter($openapi->requestBody->content, function ($a) use ($mediaType) {
//                            return $a->mediaType == $mediaType;
//                        })[0] ?? null;
//
//                    if ([] === $parsedBody) {
//                        $payload = new \stdClass();
//                    } else {
//                        $payload = json_encode($parsedBody);
//                        $payload = (object) json_decode($payload);
//                    }
//
//                    $jsonSchema = json_decode($content->schema->toJson(), true);
//                    $jsonSchema = $this->getRef($jsonSchema);
//                    dd($jsonSchema);

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
