<?php

namespace Chargefield\Store\Http;

use Chargefield\Store\Masters\Masters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET /api/store/master-file/{id}: Studio's masters library asks for one of
 * this site's masters when someone downloads it. Answers with a short-lived
 * signed URL; the file itself never passes through Studio.
 *
 * Bearer-authed with the catalogue secret.
 */
class MasterFileController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $secret = (string) config('store.catalog.secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->bearerToken()), 403, 'Forbidden');

        $file = Masters::model()::query()->find($id);
        abort_if($file === null, 404);

        $url = $file->temporaryUrl(30);

        return filled($url)
            ? response()->json(['url' => $url, 'filename' => basename($file->path)])
            : response()->json(['url' => null, 'error' => 'This disk cannot sign download links.'], 404);
    }
}
