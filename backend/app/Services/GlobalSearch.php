<?php

namespace App\Services;

use App\Enums\TopupStatus;
use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Category;
use App\Models\DelegateSettlement;
use App\Models\DeliveryZone;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\UserType;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\Warehouse;
use App\Support\ArabicText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One search box over everything the dashboard holds.
 *
 * - Every word must match, but each word may match a different field, even one
 *   on a related row: "احمد طرابلس" finds Ahmed's orders delivered to Tripoli,
 *   and "بن 500" finds the 500 g size of the coffee product.
 * - Matching is Arabic-tolerant (ArabicText), so spelling variants and
 *   Arabic-Indic digits find the same rows.
 * - A group is searched only when the user holds that module's VIEW code, the
 *   same code its list page needs, so search can never show what the pages hide.
 * - Rows whose main field starts with the query come first, then the newest.
 *
 * A source is: who may see it, where to look, and how a row reads as a result.
 * Adding something new to search is adding one entry to sources().
 */
class GlobalSearch
{
    public const MIN_LENGTH = 2;

    private const MAX_TERMS = 5;

    private const TOPUP_LABELS = ['pending' => 'قيد المراجعة', 'approved' => 'مقبول', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي', 'failed' => 'فشل'];

    public function run(AppUser $user, string $raw, ?string $only = null, int $limit = 5): array
    {
        $terms = $this->terms($raw);
        if ($terms === []) {
            return ['query' => trim($raw), 'total' => 0, 'groups' => []];
        }

        $codes = $this->codes($user);
        $groups = [];

        foreach ($this->sources($user) as $key => $source) {
            if ($only !== null && $only !== $key) {
                continue;
            }
            if ($source['permission'] !== null && $codes !== null && ! in_array($source['permission'], $codes, true)) {
                continue;
            }

            /** @var Builder $query */
            $query = $source['query'];
            foreach ($terms as $term) {
                $query->where(function (Builder $w) use ($term, $source) {
                    ArabicText::filter($w, $term, $source['columns']);
                    foreach ($source['relations'] ?? [] as $relation => $columns) {
                        $w->orWhereHas($relation, fn (Builder $r) => ArabicText::filter($r, $term, $columns));
                    }
                    // "52" or "#52" also means "the row numbered 52".
                    if (($source['by_id'] ?? false) && ctype_digit($term)) {
                        $w->orWhere($w->getModel()->getQualifiedKeyName(), (int) $term);
                    }
                });
            }

            // Best first: the main field starts with the query, then any of the
            // row's own fields holds it, then rows found only through a relation
            // (an order found because its delegate's name matched).
            if ($source['columns'] !== []) {
                $term = addcslashes($terms[0], '%_\\');
                $own = implode(' OR ', array_map(fn ($c) => ArabicText::sqlExpression($c).' LIKE ?', $source['columns']));
                $query->orderByRaw(
                    'CASE WHEN '.ArabicText::sqlExpression($source['columns'][0])." LIKE ? THEN 0 WHEN {$own} THEN 1 ELSE 2 END",
                    [$term.'%', ...array_fill(0, count($source['columns']), '%'.$term.'%')],
                );
            }

            $rows = $query->orderByDesc($query->getModel()->getQualifiedKeyName())->limit($limit + 1)->get();
            if ($rows->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => $source['label'],
                'has_more' => $rows->count() > $limit,
                'items' => $rows->take($limit)->map(fn (Model $row) => ['id' => $row->getKey()] + ($source['item'])($row))->values()->all(),
            ];
        }

        return [
            'query' => trim($raw),
            'total' => array_sum(array_map(fn ($g) => count($g['items']), $groups)),
            'groups' => $groups,
        ];
    }

    /** Every searchable group with its label, for the scope chips; filtered by what the user may see. */
    public function scopes(AppUser $user): array
    {
        $codes = $this->codes($user);

        return collect($this->sources($user))
            ->filter(fn ($s) => $s['permission'] === null || $codes === null || in_array($s['permission'], $codes, true))
            ->map(fn ($s, $key) => ['key' => $key, 'label' => $s['label']])
            ->values()->all();
    }

    /** Folded, de-duplicated words of the query; empty when there is too little to search for. */
    private function terms(string $raw): array
    {
        $clean = ArabicText::normalize(ltrim(trim($raw), '#'));
        $terms = array_values(array_unique(array_filter(preg_split('/\s+/u', $clean) ?: [], fn ($t) => $t !== '')));

        // One digit is a fair query ("order 7"); one letter is not.
        if (mb_strlen($clean) < self::MIN_LENGTH && ! ctype_digit($clean)) {
            return [];
        }

        return array_slice($terms, 0, self::MAX_TERMS);
    }

    /** Permission codes the user holds; null means "all of them" (super admin). */
    private function codes(AppUser $user): ?array
    {
        if ($user->userType?->name === 'super_admin') {
            return null;
        }

        return $user->loadMissing('userType.permissions')->userType?->permissions?->pluck('code')->all() ?? [];
    }

    private function sources(AppUser $user): array
    {
        $money = fn ($v) => number_format((float) $v, 2).' د.ل';
        $join = fn (...$parts) => implode(' · ', array_filter($parts, fn ($p) => $p !== null && $p !== ''));
        $person = ['name', 'mobile_number', 'email'];
        $ofType = fn (array $names, bool $in = true) => AppUser::query()->whereHas('userType', fn ($t) => $in ? $t->whereIn('name', $names) : $t->whereNotIn('name', $names));

        return [
            'orders' => [
                'label' => 'الطلبات', 'permission' => 'ORDERS_VIEW', 'by_id' => true,
                'query' => Order::query()->with('user:id,name'),
                'columns' => ['order_number', 'delivery_address_name', 'delivery_city', 'delivery_street', 'CAST(delivery_phones AS CHAR)'],
                'relations' => ['user' => $person, 'delegate' => ['name', 'mobile_number']],
                'item' => fn (Order $o) => ['title' => $o->order_number, 'subtitle' => $join($o->user?->name, $o->delivery_city, $money($o->total_amount)), 'badge' => $o->status->label(), 'url' => "/orders/{$o->id}"],
            ],
            'customers' => [
                'label' => 'المقاهي', 'permission' => 'USERS_VIEW', 'by_id' => true,
                'query' => $ofType(['customer'])->with('customerProfile:id,user_id,business_name'),
                'columns' => $person,
                'relations' => ['customerProfile' => ['business_name']],
                'item' => fn (AppUser $u) => ['title' => $u->name, 'subtitle' => $join($u->customerProfile?->business_name, $u->mobile_number), 'badge' => $u->is_active ? null : 'غير نشط', 'url' => "/users/{$u->id}"],
            ],
            'delegates' => [
                'label' => 'المناديب', 'permission' => 'DELEGATES_VIEW', 'by_id' => true,
                'query' => $ofType(['delegate']),
                'columns' => $person,
                'item' => fn (AppUser $u) => ['title' => $u->name, 'subtitle' => $u->mobile_number, 'badge' => $u->is_active ? null : 'موقوف', 'url' => "/delegates/{$u->id}"],
            ],
            'staff' => [
                'label' => 'فريق الإدارة', 'permission' => 'USERS_VIEW', 'by_id' => true,
                'query' => $ofType(['customer', 'delegate'], false)->with('userType:id,name'),
                'columns' => $person,
                'item' => fn (AppUser $u) => ['title' => $u->name, 'subtitle' => $join($u->email, $u->mobile_number), 'badge' => $u->userType?->name, 'url' => "/users/{$u->id}"],
            ],
            'products' => [
                'label' => 'المنتجات', 'permission' => 'PRODUCTS_VIEW', 'by_id' => true,
                'query' => Product::query()->with('category:id,name'),
                'columns' => ['name', 'brand', 'description', 'CAST(tags AS CHAR)'],
                'relations' => ['category' => ['name']],
                'item' => fn (Product $p) => ['title' => $p->name, 'subtitle' => $join($p->brand, $p->category?->name), 'badge' => $p->is_active ? null : 'مخفي', 'url' => "/products/{$p->id}"],
            ],
            'variants' => [
                'label' => 'الأحجام والأصناف', 'permission' => 'PRODUCTS_VIEW',
                'query' => ProductVariant::query()->with('product:id,name'),
                'columns' => ['sku', 'barcode', 'name'],
                'relations' => ['product' => ['name', 'brand']],
                'item' => fn (ProductVariant $v) => ['title' => $join($v->product?->name, $v->name), 'subtitle' => $join($v->sku, $money($v->price)), 'badge' => $v->is_active ? null : 'مخفي', 'url' => "/product-variants/{$v->id}"],
            ],
            'categories' => [
                'label' => 'التصنيفات', 'permission' => 'CATEGORIES_VIEW',
                'query' => Category::query(),
                'columns' => ['name'],
                'item' => fn (Category $c) => ['title' => $c->name, 'subtitle' => null, 'badge' => null, 'url' => '/categories'],
            ],
            'addresses' => [
                'label' => 'العناوين', 'permission' => 'CUSTOMER_BRANCHES_VIEW',
                'query' => Address::query()->with('user:id,name'),
                'columns' => ['name', 'city', 'street', 'CAST(contact_phones AS CHAR)'],
                'relations' => ['user' => ['name', 'mobile_number']],
                'item' => fn (Address $a) => ['title' => $a->name, 'subtitle' => $join($a->user?->name, $a->city, $a->street), 'badge' => null, 'url' => "/addresses/{$a->id}"],
            ],
            'warehouses' => [
                'label' => 'المستودعات', 'permission' => 'WAREHOUSES_VIEW',
                'query' => Warehouse::query(),
                'columns' => ['name', 'city'],
                'item' => fn (Warehouse $w) => ['title' => $w->name, 'subtitle' => $w->city, 'badge' => null, 'url' => "/warehouses/{$w->id}"],
            ],
            'zones' => [
                'label' => 'مناطق التوصيل', 'permission' => 'DELIVERY_ZONES_VIEW',
                'query' => DeliveryZone::query(),
                'columns' => ['name'],
                'item' => fn (DeliveryZone $z) => ['title' => $z->name, 'subtitle' => 'توصيل '.$money($z->delivery_price), 'badge' => $z->is_active ? null : 'متوقفة', 'url' => "/delivery-zones/{$z->id}"],
            ],
            'topups' => [
                'label' => 'طلبات الشحن', 'permission' => 'WALLET_TOPUPS_VIEW', 'by_id' => true,
                'query' => WalletTopup::query()->with('user:id,name'),
                'columns' => ['reference_number', 'gateway_reference', 'note'],
                'relations' => ['user' => ['name', 'mobile_number']],
                'item' => fn (WalletTopup $t) => ['title' => $join($t->user?->name, $money($t->amount)), 'subtitle' => $t->reference_number, 'badge' => self::TOPUP_LABELS[$t->status instanceof TopupStatus ? $t->status->value : $t->status] ?? null, 'url' => "/wallet-topups/{$t->id}"],
            ],
            'wallets' => [
                'label' => 'المحافظ', 'permission' => 'WALLETS_VIEW',
                'query' => Wallet::query()->with('user:id,name,mobile_number'),
                'columns' => [],
                'relations' => ['user' => ['name', 'mobile_number']],
                'item' => fn (Wallet $w) => ['title' => 'محفظة '.($w->user?->name ?? ''), 'subtitle' => $join($w->user?->mobile_number, $money($w->balance)), 'badge' => null, 'url' => "/wallets/{$w->id}"],
            ],
            'settlements' => [
                'label' => 'تسكيرات العهدة', 'permission' => 'CUSTODY_VIEW',
                'query' => DelegateSettlement::query()->with('delegate:id,name'),
                'columns' => ['reference_number', 'note'],
                'relations' => ['delegate' => ['name', 'mobile_number']],
                'item' => fn (DelegateSettlement $s) => ['title' => $s->reference_number, 'subtitle' => $join($s->delegate?->name, $money($s->amount)), 'badge' => null, 'url' => "/custody/{$s->delegate_id}"],
            ],
            'returns' => [
                'label' => 'المرتجعات', 'permission' => 'RETURNS_VIEW',
                'query' => OrderReturn::query()->with('order:id,order_number'),
                'columns' => ['reason'],
                'relations' => ['order' => ['order_number'], 'order.user' => ['name', 'mobile_number']],
                'item' => fn (OrderReturn $r) => ['title' => $r->reason, 'subtitle' => $join($r->order?->order_number, $money($r->total_value)), 'badge' => null, 'url' => "/orders/{$r->order_id}"],
            ],
            'promos' => [
                'label' => 'البروموهات', 'permission' => 'PROMOS_VIEW',
                'query' => Promo::query(),
                'columns' => ['description', 'link'],
                'item' => fn (Promo $p) => ['title' => $p->description ?: 'برومو', 'subtitle' => $p->link, 'badge' => $p->is_active ? null : 'متوقف', 'url' => '/promos'],
            ],
            'roles' => [
                'label' => 'الأدوار', 'permission' => 'USER_TYPES_VIEW',
                'query' => UserType::query(),
                'columns' => ['name'],
                'item' => fn (UserType $t) => ['title' => $t->name, 'subtitle' => null, 'badge' => null, 'url' => "/user-types/{$t->id}"],
            ],
            'notifications' => [
                // Your own inbox, so no module code applies.
                'label' => 'إشعاراتي', 'permission' => null,
                'query' => Notification::query()->where('user_id', $user->id),
                'columns' => ['title', 'message'],
                'item' => fn (Notification $n) => ['title' => $n->title, 'subtitle' => $n->message, 'badge' => $n->read_at ? null : 'جديد', 'url' => "/notifications/{$n->id}"],
            ],
            'activity' => [
                'label' => 'سجل النشاطات', 'permission' => 'ACTIVITY_LOGS_VIEW',
                'query' => ActivityLog::query(),
                'columns' => ['description', 'user_name', 'action'],
                'item' => fn (ActivityLog $l) => ['title' => $l->description ?: $l->action, 'subtitle' => $join($l->user_name, $l->created_at?->format('Y-m-d H:i')), 'badge' => null, 'url' => '/activity-logs'],
            ],
        ];
    }
}
