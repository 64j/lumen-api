<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Collective\Annotations\Routing\Annotations\Annotations\Get;
use Collective\Annotations\Routing\Annotations\Annotations\Post;
use OpenApi\Annotations as OA;

class HomeController extends Controller
{
    /**
     * @Get("/home",
     *     as="home",
     * )
     * @Post(
     *     "/home",
     *     middleware={"App\Http\Middleware\OpenApiMiddleware"}
     * )
     * @OA\Post(
     *     path="/home",
     *     summary="Adds a new user - with oneOf examples",
     *     description="Adds a new user",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="integer"
     *                 ),
     *                 example={"id": "a3fb6", "name": "Jessica Smith", "phone": 12345678}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Examples(example="result", value={"success": true}, summary="An result object."),
     *             @OA\Examples(example="bool", value=false, summary="A boolean value."),
     *         )
     *     )
     * )
     */
    public function index()
    {
        return 'ok';
    }

    /**
     * @Get(
     *     "/home2",
     *     as="home2",
     * )
     * @Post(
     *     "/home2",
     *     as="home2",
     *     middleware={"App\Http\Middleware\OpenApiMiddleware"}
     * )
     * @OA\Post(
     *     path="/home2",
     *     @OA\RequestBody(ref="/provided-schema/example.json"),
     *     @OA\Response(
     *         response=200,
     *         description="successful operation",
     *         @OA\JsonContent(
     *             required={"id", "name", "phone"},
     *             properties={
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="integer"
     *                 ),
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="oops"
     *     )
     * )
     * @return array
     */
    public function index2(): array
    {
        return [
            'id' => 123,
            'name' => 'Name',
            'phone' => 456,
        ];
    }
}
