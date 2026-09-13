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
 * @OA\Tag(name="Cafe Products", description="التصنيفات، المنتجات والبحث، تفاصيل المنتج وأحجامه، والأقسام المميزة المختارة من الإدارة.")
 * @OA\Tag(name="Cafe Favorites", description="إضافة المنتجات للمفضلة وعرضها. كل كارت منتج يحمل `is_favorite`.")
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
