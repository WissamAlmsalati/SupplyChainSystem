<?php

namespace App\OpenApi;

/**
 * Sections of the Cafe Mobile API docs, in the order they appear in Scalar.
 * Every customer-app endpoint uses one of these tags (config/l5-swagger.php
 * keeps "Cafe …" tags, Auth and Notifications in the cafe documentation).
 *
 * @OA\Tag(name="Auth", description="الدخول، التسجيل برقم الهاتف والتحقق (OTP)، استعادة كلمة المرور، `me` والخروج.")
 * @OA\Tag(name="Cafe Profile", description="الملف الشخصي للمقهى، لوحة الإحصائيات، والميزات المفعّلة في التطبيق.")
 * @OA\Tag(name="Cafe Promos", description="بانرات العروض النشطة للصفحة الرئيسية.")
 * @OA\Tag(
 *     name="Cafe Products",
 *     description="التصنيفات، المنتجات والبحث بالفلاتر، تفاصيل المنتج وأحجامه، والأقسام المميزة.

## أقسام الرئيسية و«عرض الكل»

**1. الرئيسية** — `GET /cafe/featured-sections`

كل قسم يرجع أول N منتج فقط (N يحدده الأدمن) مع:
- `products_total`: عدد كل منتجات القسم
- `has_more`: `true` إذا كان هناك منتجات أكثر من المعروضة

اعرض عنوان القسم ومنتجاته، وإذا كان `has_more = true` أضف زر **«عرض الكل (products_total)»**.

**2. شاشة «عرض الكل»** — `GET /cafe/featured-sections/{id}/products?page=1&per_page=20`

```json
{
  ""section"": { ""id"": 1, ""title"": ""الأكثر مبيعاً"", ""source"": ""filter"", ""sort"": ""popular"" },
  ""data"": [ ""...كروت المنتجات..."" ],
  ""meta"": { ""current_page"": 1, ""per_page"": 20, ""total"": 12, ""last_page"": 1 }
}
```

العنوان من `section.title` والعدد من `meta.total`.

**3. تحميل المزيد (infinite scroll)** — عند الوصول لآخر القائمة وطالما `meta.current_page < meta.last_page` اطلب `page` التالية وأضف النتائج تحت الموجودة.

**ملاحظات**
- الصفحة الأولى تبدأ من بداية القسم (تشمل منتجات الرئيسية نفسها بنفس الترتيب) — اعرض الرد كما هو ولا تدمجه مع منتجات الرئيسية.
- الترتيب ثابت حسب نوع القسم: الأكثر مبيعاً بالمبيعات، الأقل سعراً بالسعر، واليدوي بترتيب الأدمن.
- القسم المخفي أو المحذوف يرجع `404` — ارجع للرئيسية أو اعرض «القسم غير متاح».
- الكارت نفسه في كل الشاشات: `is_favorite` للقلب، `in_stock` للتوفر، و`default_variant_id` لإضافة سريعة عبر `POST /cafe/cart/items`.
- `per_page` الافتراضي 20 والحد الأقصى 100."
 * )
 * @OA\Tag(
 *     name="Cafe Favorites",
 *     description="إضافة المنتجات للمفضلة وإزالتها وعرضها.

## القلب الأحمر `is_favorite`

كل منتج يرجع للتطبيق يحمل `is_favorite` (`true` = اعرض القلب أحمر) للمستخدم الحالي:

| المكان | الحقل |
|---|---|
| قائمة المنتجات والبحث `GET /cafe/products` | `data[].is_favorite` |
| تفاصيل المنتج `GET /cafe/products/{id}` | `is_favorite` |
| أقسام الرئيسية `GET /cafe/featured-sections` | `data[].products[].is_favorite` |
| عرض الكل `GET /cafe/featured-sections/{id}/products` | `data[].is_favorite` |
| المفضلة `GET /cafe/favorites` | `data[].is_favorite` (دائماً `true`) |
| السلة `GET /cafe/cart` | `data.items[].product_variant.product.is_favorite` |
| الطلبيات المتكررة `GET /cafe/recurring-carts` و `/{id}` | `items[].product_variant.product.is_favorite` |

**عند الضغط على القلب:** `POST /cafe/favorites` بـ `{ ""product_id"": 5 }` للإضافة، و `DELETE /cafe/favorites/{productId}` للإزالة. الاستدعاءان آمنان للتكرار ويرجعان `is_favorite` و `favorites_count`، فحدّث القلب محلياً مباشرة دون إعادة تحميل القائمة.

لتلوين القلوب في شاشة لا تحمل الحقل يمكن جلب الأرقام مرة واحدة من `GET /cafe/favorites/ids`."
 * )
 * @OA\Tag(name="Cafe Cart", description="سلة المشتريات: الإضافة والتعديل، فحص المخزون، وإتمام الطلب (نقداً عند الاستلام أو من المحفظة).")
 * @OA\Tag(name="Cafe Recurring Carts", description="الطلبيات المتكررة: سلال بأسماء يعاد طلبها بضغطة واحدة.")
 * @OA\Tag(name="Cafe Orders", description="الطلبات: الإنشاء المباشر، القائمة والتفاصيل، تأكيد الاستلام، طلب الإلغاء وموقع المندوب.")
 * @OA\Tag(name="Cafe Addresses", description="عناوين التوصيل ومناطق التوصيل على الخريطة.")
 * @OA\Tag(name="Cafe Wallet", description="المحفظة: الرصيد والحركات، الشحن بتحويل بنكي (مع الإيصال) أو إلكترونياً عبر البوابة.")
 * @OA\Tag(name="Notifications", description="إشعارات المستخدم: القائمة، غير المقروءة، وتحديدها كمقروءة.")
 */
class CafeTags
{
    //
}
