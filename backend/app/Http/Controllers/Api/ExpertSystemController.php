<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ExpertSystemController extends Controller
{
    /**
     * Evaluate the medical expert system for the submitted symptoms.
     *
     * Authorization uses the existing `expert-system.use` permission. Symptom
     * identifiers are validated locally before any data is forwarded, and the
     * upstream response is validated before it is returned to the client.
     */
    public function evaluate(Request $request): JsonResponse
    {
        if (! $request->user()?->can('expert-system.use')) {
            return response()->json([
                'message' => 'You are not authorized to use the expert system.',
            ], 403);
        }

        $validated = $request->validate([
            'symptom_ids' => ['required', 'array', 'min:1', 'max:50'],
            'symptom_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('symptoms', 'id')->where('is_active', true),
            ],
        ]);

        $symptomIds = array_values(array_unique(array_map('intval', $validated['symptom_ids'])));

        $config = config('services.expert_system');
        $url = rtrim((string) $config['url'], '/').'/evaluate';

        try {
            $response = Http::withHeaders(array_filter([
                'Accept' => 'application/json',
                'X-Internal-Api-Key' => $config['api_key'] ?: null,
            ]))
                ->timeout((int) $config['timeout'])
                ->connectTimeout((int) $config['connect_timeout'])
                ->post($url, [
                    'symptom_ids' => $symptomIds,
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'The expert system service is unavailable.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'The expert system service returned an error.',
            ], 502);
        }

        $data = $response->json();

        if (! $this->isValidExpertSystemResponse($data)) {
            return response()->json([
                'message' => 'The expert system service returned an invalid response.',
            ], 502);
        }

        return response()->json([
            'message' => 'Expert system evaluation completed.',
            'data' => $data,
        ]);
    }

    /**
     * Validate the shape of the upstream response before exposing it.
     */
    private function isValidExpertSystemResponse(mixed $data): bool
    {
        return is_array($data)
            && array_key_exists('matched_rule_count', $data)
            && is_int($data['matched_rule_count'])
            && array_key_exists('matched_rules', $data)
            && is_array($data['matched_rules']);
    }
}
