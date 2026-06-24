<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateEmailRequest;
use Illuminate\Http\JsonResponse;

class ValidateEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('throttle:30,1');
    }

    public function __invoke(ValidateEmailRequest $request): JsonResponse
    {
        return response()->json([
            'valid' => true,
        ]);
    }
}
