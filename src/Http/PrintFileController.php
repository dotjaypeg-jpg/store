<?php

namespace Chargefield\Store\Http;

use Chargefield\Store\Catalog\Carrying;
use Chargefield\Store\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GET /api/store/print-file/{id}: Studio asks for one of this store's
 * products' print file, because another store carries it and has an order to
 * print. Answers with a short-lived signed URL from the site's
 * `store.catalog.print_file` callable; the master itself never leaves.
 *
 * Bearer-authed with the catalogue secret (the one Studio shares with every
 * store). Only this store's own products: a carried copy has no master here.
 */
class PrintFileController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $secret = (string) config('store.catalog.secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->bearerToken()), 403, 'Forbidden');

        $callable = config('store.catalog.print_file');
        abort_if(blank($callable), 404);

        $product = Store::model('product')::query()->find($id);
        abort_if($product === null || Carrying::isCarried($product), 404);

        $url = call_user_func($callable, $product);

        return filled($url)
            ? response()->json(['url' => $url])
            : response()->json(['url' => null, 'error' => 'No print master for this product.'], 404);
    }
}
