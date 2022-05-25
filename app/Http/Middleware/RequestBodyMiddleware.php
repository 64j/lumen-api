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

        $route = app()->router->getRoutes()[$request->getMethod() . $request->getPathInfo()] ?? null;

        if ($route) {
            [$class, $method] = Str::parseCallback($route['action']['uses']);

            $reader = new AnnotationReader();
            $annotations = $reader->getMethodAnnotations(new ReflectionMethod($class, $method));
            $annotations = array_values(array_filter($annotations, function ($a) use ($request) {
                return $a instanceof AbstractAnnotation && $request->isMethod((string) $a->method);
            }));

            if (!empty($annotations[0])) {
                /** @var AbstractAnnotation $openapi */
                $openapi = $annotations[0];

                if ($openapi->requestBody != Generator::UNDEFINED) {
                    $mediaType = $request->header('content-type');
                    $content = array_filter($openapi->requestBody->content, function ($a) use ($mediaType) {
                            return $a->mediaType == $mediaType;
                        })[0] ?? null;

                    if ([] === $parsedBody) {
                        $payload = new \stdClass();
                    } else {
                        $payload = json_encode($parsedBody);
                        $payload = (object) json_decode($payload);
                    }

                    $content = json_decode($content->schema->toJson(), true);

                    $validator = new Validator();
                    $validator->validate($payload, $content);

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
