<?php

namespace Webkul\Torob\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webkul\Torob\Services\TorobProductService;

class ProductController
{
    public function __construct(
        protected TorobProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(
                $this->productService->handle($request)
            );
        } catch (BadRequestHttpException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 400);
        }
    }
}