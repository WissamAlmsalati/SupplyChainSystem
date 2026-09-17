<?php

namespace App\Http\Controllers\Api;

use App\Support\Placeholder;
use Illuminate\Http\Response;

class PlaceholderController extends BaseApiController
{
    /**
     * @OA\Get(path="/placeholder/{kind}", tags={"Customer Products"}, summary="Default image used when a record has no picture",
     *     description="Public SVG placeholder. image_url falls back to this URL, so clients can always render an image. Kinds: product.svg, promo.svg, category.svg, customer.svg, user.svg.",
     *     @OA\Parameter(name="kind", in="path", required=true, @OA\Schema(type="string", example="product.svg")),
     *     @OA\Response(response=200, description="SVG image"))
     */
    public function show(string $kind): Response
    {
        return response(Placeholder::svg(pathinfo($kind, PATHINFO_FILENAME)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
