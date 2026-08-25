<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Cafe Supply Chain API",
 *     description="REST API documentation split by platform: Admin Dashboard, Cafe Mobile App, and Public Storefront.",
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="Local development server"
 * )
 *
 * @OA\Tag(name="Auth", description="Shared authentication endpoints"),
 * @OA\Tag(name="Admin Dashboard", description="Admin platform analytics"),
 * @OA\Tag(name="Admin Users", description="Admin platform user management"),
 * @OA\Tag(name="Admin Cafes", description="Admin platform cafe management"),
 * @OA\Tag(name="Admin Branches", description="Admin platform branch management"),
 * @OA\Tag(name="Admin Orders", description="Admin platform order management"),
 * @OA\Tag(name="Admin Inventory", description="Admin platform inventory management"),
 * @OA\Tag(name="Admin Products", description="Admin platform product management"),
 * @OA\Tag(name="Admin Categories", description="Admin platform category management"),
 * @OA\Tag(name="Admin Suppliers", description="Admin platform supplier management"),
 * @OA\Tag(name="Admin Warehouses", description="Admin platform warehouse management"),
 * @OA\Tag(name="Admin Delivery Zones", description="Admin platform delivery zone management"),
 * @OA\Tag(name="Admin Roles", description="Admin platform roles and permissions"),
 * @OA\Tag(name="Admin Activity Logs", description="Admin platform activity logs"),
 * @OA\Tag(name="Cafe Mobile Dashboard", description="Cafe mobile app dashboard"),
 * @OA\Tag(name="Cafe Mobile Orders", description="Cafe mobile app order management"),
 * @OA\Tag(name="Cafe Mobile Branches", description="Cafe mobile app branch management"),
 * @OA\Tag(name="Cafe Mobile Profile", description="Cafe mobile app profile"),
 * @OA\Tag(name="Storefront", description="Public storefront endpoints"),
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class ApiInfo
{
    //
}
