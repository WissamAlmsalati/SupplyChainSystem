<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Search", description="One search box over everything the dashboard holds")
 */
class SearchController extends BaseApiController
{
    /**
     * @OA\Get(path="/search", tags={"Search"}, summary="Search everything the signed-in user may see",
     *     description="Every word must match, but each may match a different field, even on a related row: `احمد طرابلس` finds Ahmed's orders delivered to Tripoli. Matching is Arabic-tolerant. A group appears only when the user holds that module's VIEW permission. `#52` or `52` also finds the row with that id. Dashboard accounts only; the customer and delegate apps are refused.",
     *
     *     @OA\Parameter(name="q", in="query", required=true, description="At least 2 characters, or a number", @OA\Schema(type="string")),
     *     @OA\Parameter(name="only", in="query", description="Search one group and return more of it (see `scopes` in the answer for the keys)", @OA\Schema(type="string", example="orders")),
     *
     *     @OA\Response(response=200, description="Matches grouped by kind. `url` is the dashboard page to open. `has_more` means the group holds more than was returned; ask again with `only`.",
     *
     *         @OA\JsonContent(example={"query": "احمد", "total": 2, "groups": {{"key": "orders", "label": "الطلبات", "has_more": false, "items": {{"id": 60, "title": "ORD-2026-09-18-12-002", "subtitle": "مقهى أحمد · طرابلس · 365.00 د.ل", "badge": "مؤكد", "url": "/orders/60"}}}, {"key": "customers", "label": "المقاهي", "has_more": false, "items": {{"id": 12, "title": "مقهى أحمد", "subtitle": "0912345678", "badge": null, "url": "/users/12"}}}}, "scopes": {{"key": "orders", "label": "الطلبات"}, {"key": "customers", "label": "المقاهي"}}})),
     *
     *     @OA\Response(response=403, description="Signed in as a customer or a delegate.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "غير مصرح"}))
     * )
     */
    public function __invoke(Request $request, GlobalSearch $search): JsonResponse
    {
        $user = $request->user();

        // ponytail: this route has no module code of its own, because what it
        // returns is decided group by group from the codes the user holds. But
        // the customer role holds ORDERS_VIEW for its own scoped endpoints, so
        // the app roles must be turned away here or a customer token would
        // search every order in the system.
        if ($user->hasRole(UserRole::Customer, UserRole::Delegate)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'only' => ['nullable', 'string', Rule::in(array_column($search->scopes($user), 'key'))],
        ]);

        $only = $data['only'] ?? null;
        // A query string cut in the middle of a character is not valid UTF-8,
        // and echoing it back would fail to encode as JSON.
        $data['q'] = mb_scrub($data['q'], 'UTF-8');

        return $this->jsonResponse($search->run($user, $data['q'], $only, $only ? 20 : 5) + ['scopes' => $search->scopes($user)]);
    }
}
