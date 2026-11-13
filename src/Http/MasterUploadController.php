<?php

namespace Chargefield\Store\Http;

use Chargefield\Store\Masters\Masters;
use Chargefield\Store\Masters\MasterVault;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Masters uploaded straight into the bucket from the computer that has them
 * (Studio's master inbox), so big files never pass through this server:
 *
 *   POST /api/store/master-uploads           -> a signed PUT link for the file's
 *        {target, role, ext, bytes, ...}        name (client/film-year/...), plus a
 *                                              token for the second call
 *   POST /api/store/master-uploads/complete  -> checks the file arrived whole and
 *        {token}                               records it on its owner
 *
 * `target` is the site's own key for what the master belongs to (chargefield: a
 * portfolio project's slug), resolved by `store.masters.upload_target`.
 * Bearer-authed with the catalogue secret.
 */
class MasterUploadController extends Controller
{
    private function authorize(Request $request): void
    {
        $secret = (string) config('store.catalog.secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->bearerToken()), 403, 'Forbidden');
    }

    public function start(Request $request): JsonResponse
    {
        $this->authorize($request);

        $data = $request->validate([
            'target' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
            'ext' => ['required', 'string', 'max:5', 'regex:/^[a-z0-9]+$/'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:100'],
            'bytes' => ['required', 'integer', 'min:1'],
            'checksum' => ['required', 'string', 'size:64'],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
        ]);

        $resolve = config('store.masters.upload_target');
        $owner = filled($resolve) ? call_user_func($resolve, $data['target']) : null;
        if (! $owner instanceof Model || ! method_exists($owner, 'masterFiles')) {
            return response()->json(['error' => "Nothing here matches \"{$data['target']}\"."], 404);
        }

        // The same file already on this owner: nothing to upload.
        $existing = $owner->masterFiles()->where('checksum', $data['checksum'])->first();
        if ($existing) {
            return response()->json(['duplicate' => true, 'id' => $existing->id, 'path' => $existing->path]);
        }

        [$dir, $stem] = MasterVault::naming($owner, $data['role']);
        $path = app(MasterVault::class)->uniquePath($dir, $stem, MasterVault::normalizeExt($data['ext']));

        try {
            $link = Storage::disk(Masters::disk())->temporaryUploadUrl($path, now()->addHours(2), [
                'ContentType' => $data['mime'] ?: 'application/octet-stream',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'This disk cannot take direct uploads: '.$e->getMessage()], 501);
        }

        $token = Crypt::encryptString(json_encode([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'role' => $data['role'],
            'path' => $path,
            'facts' => array_intersect_key($data, array_flip(['original_filename', 'mime', 'bytes', 'checksum', 'width', 'height'])),
            'expires' => now()->addHours(3)->timestamp,
        ]));

        return response()->json([
            'url' => $link['url'],
            'headers' => $link['headers'] ?? [],
            'path' => $path,
            'token' => $token,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $this->authorize($request);
        $request->validate(['token' => ['required', 'string']]);

        try {
            $t = json_decode(Crypt::decryptString((string) $request->input('token')), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return response()->json(['error' => 'Bad upload token.'], 422);
        }
        if (($t['expires'] ?? 0) < now()->timestamp) {
            return response()->json(['error' => 'Upload token expired; start again.'], 422);
        }

        $disk = Storage::disk(Masters::disk());
        if (! $disk->exists($t['path'])) {
            return response()->json(['error' => 'The file never arrived in the bucket.'], 422);
        }
        $size = (int) $disk->size($t['path']);
        if ($size !== (int) $t['facts']['bytes']) {
            return response()->json(['error' => "The file arrived incomplete ({$size} of {$t['facts']['bytes']} bytes)."], 422);
        }

        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($t['owner_type']) ?? $t['owner_type'];
        $owner = class_exists($class) ? $class::query()->find($t['owner_id']) : null;
        if (! $owner) {
            return response()->json(['error' => 'Its owner is gone.'], 404);
        }

        $existing = $owner->masterFiles()->where('path', $t['path'])->first();
        $file = $existing ?? $owner->addMaster($t['role'], $t['path'], $t['facts']);

        return response()->json(['id' => $file->id, 'path' => $file->path]);
    }
}
