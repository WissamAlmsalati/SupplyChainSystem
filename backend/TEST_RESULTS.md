PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.32
Configuration: /Users/wissamalmsalati/cafe-supply-chain/backend/phpunit.host.xml

........................................                          40 / 40 (100%)

Time: 00:01.247, Memory: 50.50 MB

Cafe Mobile Endpoints (Tests\Feature\CafeMobileEndpoints)
 ✔ Cafe login returns token and permissions
 ✔ Cafe me returns user
 ✔ Cafe profile
 ✔ Cafe branches list
 ✔ Cafe branch create
 ✔ Cafe branch orders
 ✔ Cafe orders list
 ✔ Cafe order create
 ✔ Cafe categories list
 ✔ Cafe products list
 ✔ Cafe product variants
 ✔ Cafe inventory list
 ✔ Cafe can list delivery zones for map

Delegate Endpoints (Tests\Feature\DelegateEndpoints)
 ✔ Admin can list delegates
 ✔ Admin can create delegate
 ✔ Admin can update delegate
 ✔ Admin can delete delegate
 ✔ Admin can toggle delegate active status

Delegate Mobile Endpoints (Tests\Feature\DelegateMobileEndpoints)
 ✔ Delegate can update location
 ✔ Delegate can set availability
 ✔ Order auto assigns nearest delegate
 ✔ Delegate location update broadcasts event
 ✔ Delegate can list assigned orders
 ✔ Delegate can filter assigned orders by status
 ✔ Delegate can view assigned order detail
 ✔ Delegate cannot view unassigned order detail
 ✔ Delegate can update assigned order status
 ✔ Delegate cannot update unassigned order status
 ✔ Delegate cannot set status other than delivered

Example (Tests\Feature\Example)
 ✔ The application returns a successful response

Example (Tests\Unit\Example)
 ✔ That true is true

Order Assign Delegate (Tests\Feature\OrderAssignDelegate)
 ✔ Admin can assign delegate to order
 ✔ Cannot assign inactive delegate

Premium Feature (Tests\Feature\PremiumFeature)
 ✔ Lists only active premium features
 ✔ Cannot create inventory when feature inactive
 ✔ Can create inventory when feature active
 ✔ Cannot create role when feature inactive
 ✔ Can create role when feature active

Warehouse Hex (Tests\Feature\WarehouseHexTest)
 ✔ Warehouse hex is computed from lat lng resolution
 ✔ Expand hex creates child delivery zones

OK (40 tests, 134 assertions)
