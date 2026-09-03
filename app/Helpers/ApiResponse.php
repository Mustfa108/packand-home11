<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'تمت العملية بنجاح.', int $status = 200): JsonResponse
    {
        $response = ['success' => true, 'message' => $message];
        if (!is_null($data)) {
            $response['data'] = $data;
        }
        return response()->json($response, $status);
    }

    public static function error(string $message, int $status = 422, mixed $errors = null): JsonResponse
    {
        $response = ['success' => false, 'message' => $message];
        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, string $message = 'تم تحميل البيانات بنجاح.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
