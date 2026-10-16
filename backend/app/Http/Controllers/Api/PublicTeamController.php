<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicTeamProfile;
use Illuminate\Http\JsonResponse;

/**
 * Public listing of the clinic's published team profiles.
 */
class PublicTeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PublicTeamProfile::query()
                ->published()
                ->with('photo')
                ->ordered()
                ->get(),
        ]);
    }
}
