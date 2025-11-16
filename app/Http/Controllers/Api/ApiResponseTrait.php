<?php

namespace App\Http\Controllers\Api;

trait ApiResponseTrait
{
    protected function successResponse($data, string $message = 'Success', int $code = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message = 'Error', int $code = 400, $errors = null): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function validationErrorResponse($errors, string $message = 'Validation Error'): \Illuminate\Http\JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }
}