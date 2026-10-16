<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithWebsiteMedia;
use App\Http\Controllers\Controller;
use App\Models\PublicTeamProfile;
use App\Services\Website\WebsiteMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for public "Our Team" profiles.
 */
class WebsiteTeamProfileController extends Controller
{
    use InteractsWithWebsiteMedia;

    public function __construct(private readonly WebsiteMediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('website.view');

        $query = PublicTeamProfile::query()->with('photo:id,file_path,disk');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        return response()->json($query->ordered()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('website.team.manage');

        $validated = $request->validate($this->rules(true));

        $profile = new PublicTeamProfile;
        $profile->fill(collect($validated)->except('photo')->all());
        $profile->photo_media_id = $this->resolveMediaId($request, $this->media, 'photo', null);
        $profile->save();

        return response()->json([
            'message' => 'Team profile created successfully.',
            'data' => $profile->load('photo'),
        ], 201);
    }

    public function show(PublicTeamProfile $teamProfile): JsonResponse
    {
        $this->authorize('website.view');

        return response()->json(['data' => $teamProfile->load('photo')]);
    }

    public function update(Request $request, PublicTeamProfile $teamProfile): JsonResponse
    {
        $this->authorize('website.team.manage');

        $validated = $request->validate($this->rules());

        $teamProfile->fill(collect($validated)->except('photo')->all());
        $teamProfile->photo_media_id = $this->resolveMediaId($request, $this->media, 'photo', $teamProfile->photo_media_id, 'remove_photo');
        $teamProfile->save();

        return response()->json([
            'message' => 'Team profile updated successfully.',
            'data' => $teamProfile->fresh('photo'),
        ]);
    }

    public function destroy(PublicTeamProfile $teamProfile): JsonResponse
    {
        $this->authorize('website.team.manage');

        $teamProfile->delete();

        return response()->json(['message' => 'Team profile deleted successfully.']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(bool $creating = false): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'position' => [$presence, 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo' => $this->imageRules(),
            'remove_photo' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'staff_id' => ['nullable', 'exists:staff,id'],
        ];
    }
}
