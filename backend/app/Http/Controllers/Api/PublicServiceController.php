<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

/**
 * Public listing of the clinic's published services.
 */
class PublicServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Service::query()
                ->published()
                ->with('image')
                ->ordered()
                ->get(),
        ]);
    }
}
