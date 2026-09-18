<?php

namespace App\OpenApi;

/**
 * Sections of the Customer Mobile API docs, in the order they appear in Scalar.
 * Every customer-app endpoint uses one of these tags (config/l5-swagger.php
 * keeps "Customer …" tags, Auth and Notifications in the customer documentation).
 *
 * @OA\Tag(name="Auth", description="الدخول، التسجيل برقم الهاتف والتحقق (OTP)، استعادة كلمة المرور، `me` والخروج.")
 * @OA\Tag(name="Customer Profile", description="الملف الشخصي للمقهى، لوحة الإحصائيات، والميزات المفعّلة في التطبيق.")
 * @OA\Tag(name="Customer Promos", description="بانرات العروض النشطة للصفحة الرئيسية.")
 * @OA\Tag(
 *     name="Customer Products",
 *     description="التصنيفات، المنتجات والبحث بالفلاتر، تفاصيل المنتج وأحجامه، والأقسام المميزة.

## أقسام الرئيسية و«عرض الكل»

 **1. الرئيسية** — `GET /customer/featured-sections`

 * @OA\Tag(
 *     name="Customer Favorites",
 *     description="إضافة المنتجات للمفضلة وإزالتها وعرضها.

 * @OA\Tag(name="Customer Cart", description="سلة المشتريات: الإضافة والتعديل، فحص المخزون، وإتمام الطلب (نقداً عند الاستلام أو من المحفظة).")
 * @OA\Tag(name="Customer Recurring Carts", description="الطلبيات المتكررة: سلال بأسماء يعاد طلبها بضغطة واحدة.")
 * @OA\Tag(name="Customer Orders", description="الطلبات: الإنشاء المباشر، القائمة والتفاصيل، تأكيد الاستلام، طلب الإلغاء وموقع المندوب.")
 * @OA\Tag(name="Customer Addresses", description="عناوين التوصيل ومناطق التوصيل على الخريطة.")
 * @OA\Tag(name="Customer Wallet", description="المحفظة: الرصيد والحركات، الشحن بتحويل بنكي (مع الإيصال) أو إلكترونياً عبر البوابة.")
 * @OA\Tag(name="Notifications", description="إشعارات المستخدم: القائمة، غير المقروءة، وتحديدها كمقروءة.")
 */
class CustomerTags
{
    //
}
