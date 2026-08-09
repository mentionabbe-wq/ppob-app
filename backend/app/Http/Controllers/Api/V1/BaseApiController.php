<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * @OA\Info(
 *     title="PPOB App API",
 *     version="1.0.0",
 *     description="REST API aplikasi jualan pulsa & PPOB.",
 *     @OA\Contact(email="dev@ppob.test")
 * )
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="API v1")
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
abstract class BaseApiController extends Controller
{
    /** Bentuk respons sukses yang seragam di seluruh endpoint. */
    protected function ok(mixed $data = null, string $message = 'Berhasil.', int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;

            return response()->json($payload + [
                'data' => $data->collection,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ], $status);
        }

        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return response()->json($payload + ['data' => $data], $status);
        }

        return response()->json($data === null ? $payload : $payload + ['data' => $data], $status);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil dibuat.'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function accepted(mixed $data = null, string $message = 'Permintaan sedang diproses.'): JsonResponse
    {
        return $this->ok($data, $message, 202);
    }

    protected function fail(string $message, string $code = 'ERROR', int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'errors' => $errors ?: null,
        ]), $status);
    }
}
