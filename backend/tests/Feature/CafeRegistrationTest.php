<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\PremiumFeature;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CafeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $admin;

    protected AppUser $cafeUser;

    protected Cafe $cafe;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $adminType = UserType::create(['name' => 'admin']);
        $cafeType = UserType::create(['name' => 'cafe']);
        UserType::create(['name' => 'super_admin']);

        $this->admin = AppUser::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'mobile_number' => '0900000000',
            'password_hash' => Hash::make('password'),
            'user_type_id' => $adminType->id,
            'is_active' => true,
        ]);

        $this->cafe = Cafe::create([
            'name' => 'مقهى نشط',
            'contact_info' => '0911111111',
            'is_active' => true,
        ]);

        $this->cafeUser = AppUser::create([
            'name' => 'Cafe Owner',
            'email' => 'cafe@test.com',
            'mobile_number' => '0911111111',
            'password_hash' => Hash::make('password'),
            'user_type_id' => $cafeType->id,
            'cafe_id' => $this->cafe->id,
            'is_active' => true,
        ]);
    }

    public function test_register_creates_user_only_without_cafe(): void
    {
        $response = $this->postJson('/api/v1/cafe/register', [
            'name' => 'صاحب مقهى',
            'phone_number' => '0922222222',
            'password' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'تم إنشاء الحساب بنجاح، يمكنك تسجيل الدخول الآن')
            ->assertJsonPath('data.user.name', 'صاحب مقهى')
            ->assertJsonPath('data.user.phone_number', '0922222222')
            ->assertJsonMissingPath('data.user.id');

        $this->assertDatabaseHas('app_user', [
            'name' => 'صاحب مقهى',
            'mobile_number' => '0922222222',
            'cafe_id' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('cafe', 1); // only the seeded cafe
    }

    public function test_registration_requires_mandatory_fields(): void
    {
        $this->postJson('/api/v1/cafe/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone_number', 'password'])
            ->assertJsonMissingValidationErrors(['address', 'cafe_name']);
    }

    public function test_registration_accepts_optional_email(): void
    {
        $this->postJson('/api/v1/cafe/register', [
            'name' => 'صاحب مقهى',
            'phone_number' => '0922222222',
            'email' => 'newcafe@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        $this->assertDatabaseHas('app_user', [
            'email' => 'newcafe@example.com',
            'mobile_number' => '0922222222',
        ]);
    }

    public function test_new_user_can_login_and_has_cafe_is_false(): void
    {
        $this->postJson('/api/v1/cafe/register', [
            'name' => 'صاحب مقهى',
            'phone_number' => '0922222222',
            'password' => 'secret123',
        ])->assertCreated();

        $login = $this->postJson('/api/v1/login', [
            'phone_number' => '0922222222',
            'password' => 'secret123',
        ]);

        $login->assertOk()->assertJsonPath('has_cafe', false);

        $this->getJson('/api/v1/cafe/profile', $this->auth($login->json('token')))->assertOk()
            ->assertJsonPath('has_cafe', false)
            ->assertJsonPath('cafe', null)
            ->assertJsonPath('user.name', 'صاحب مقهى');
    }

    public function test_login_reports_has_cafe_true_for_existing_cafe_user(): void
    {
        $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('has_cafe', true);
    }

    public function test_user_without_cafe_is_blocked_from_cafe_endpoints(): void
    {
        $token = $this->registerAndLogin('0922222222');

        $this->getJson('/api/v1/cafe/orders', $this->auth($token))
            ->assertForbidden()
            ->assertJsonPath('has_cafe', false)
            ->assertJsonPath('message', 'يجب إضافة بيانات المقهى أولاً');

        $this->getJson('/api/v1/cafe/dashboard', $this->auth($token))
            ->assertForbidden();
    }

    public function test_user_can_add_cafe_after_login_pending_approval(): void
    {
        Storage::fake('public');
        $token = $this->registerAndLogin('0922222222');

        $response = $this->postJson('/api/v1/cafe/profile', [
            'name' => 'مقهى جديد',
            'logo' => UploadedFile::fake()->image('logo.jpg'),
        ], $this->auth($token));

        $response->assertCreated()
            ->assertJsonPath('message', 'تم إرسال طلب التسجيل بنجاح، سيتم التواصل معك بعد الموافقة')
            ->assertJsonPath('data.cafe.name', 'مقهى جديد')
            ->assertJsonPath('data.cafe.contact_info', '0922222222')
            ->assertJsonPath('data.cafe.is_active', false);

        $cafe = Cafe::where('name', 'مقهى جديد')->first();
        $this->assertNotNull($cafe->image);
        Storage::disk('public')->assertExists($cafe->image);

        $this->assertDatabaseHas('app_user', [
            'mobile_number' => '0922222222',
            'cafe_id' => $cafe->id,
        ]);

        // Profile now reports the cafe, but ordering stays blocked until approval.
        $this->getJson('/api/v1/cafe/profile', $this->auth($token))
            ->assertOk()
            ->assertJsonPath('has_cafe', true)
            ->assertJsonPath('cafe.name', 'مقهى جديد');

        $this->getJson('/api/v1/cafe/orders', $this->auth($token))
            ->assertForbidden()
            ->assertJsonPath('cafe_active', false)
            ->assertJsonPath('message', 'المقهى بانتظار موافقة الإدارة');
    }

    public function test_user_cannot_add_second_cafe(): void
    {
        $token = $this->cafeToken();

        $this->postJson('/api/v1/cafe/profile', ['name' => 'مقهى ثاني'], $this->auth($token))->assertStatus(409);
    }

    public function test_add_cafe_requires_name(): void
    {
        $token = $this->registerAndLogin('0922222222');

        $this->postJson('/api/v1/cafe/profile', [], $this->auth($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_auto_approve_activates_cafe_immediately(): void
    {
        PremiumFeature::updateOrCreate(
            ['code' => 'cafe_auto_approve'],
            ['name' => 'تفعيل تلقائي للمقاهي', 'is_active' => true]
        );

        $token = $this->registerAndLogin('0922222224');

        $this->postJson('/api/v1/cafe/profile', ['name' => 'مقهى تلقائي'], $this->auth($token))->assertCreated()
            ->assertJsonPath('message', 'تم تسجيل مقهاك وتفعيله')
            ->assertJsonPath('data.cafe.is_active', true);

        $this->getJson('/api/v1/cafe/orders', $this->auth($token))
            ->assertOk();
    }

    public function test_login_with_phone_number_works_for_approved_cafe(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('token', $response->json());
    }

    public function test_admin_can_list_pending_registrations(): void
    {
        $token = $this->registerAndLogin('0944444444');
        $this->postJson('/api/v1/cafe/profile', ['name' => 'Pending Cafe'], $this->auth($token))->assertCreated();

        $response = $this->getJson('/api/v1/cafe-registrations/pending', $this->auth($this->adminToken()));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Pending Cafe')
            ->assertJsonPath('data.0.app_users.0.mobile_number', '0944444444');
    }

    public function test_admin_can_approve_cafe_registration(): void
    {
        $token = $this->registerAndLogin('0955555555');
        $this->postJson('/api/v1/cafe/profile', ['name' => 'Pending Cafe'], $this->auth($token))->assertCreated();
        $pendingCafe = Cafe::where('name', 'Pending Cafe')->first();

        $this->postJson("/api/v1/cafe-registrations/{$pendingCafe->id}/approve", [], $this->auth($this->adminToken()))->assertOk()
            ->assertJsonPath('message', 'تمت الموافقة على الطلب بنجاح');

        $this->assertTrue($pendingCafe->fresh()->is_active);

        $this->getJson('/api/v1/cafe/orders', $this->auth($token))
            ->assertOk();
    }

    public function test_admin_can_reject_cafe_registration_and_user_keeps_account(): void
    {
        $token = $this->registerAndLogin('0966666666');
        $this->postJson('/api/v1/cafe/profile', ['name' => 'Rejected Cafe'], $this->auth($token))->assertCreated();
        $pendingCafe = Cafe::where('name', 'Rejected Cafe')->first();

        $this->postJson("/api/v1/cafe-registrations/{$pendingCafe->id}/reject", [], $this->auth($this->adminToken()))->assertOk()
            ->assertJsonPath('message', 'تم رفض الطلب بنجاح');

        $this->assertDatabaseMissing('cafe', ['name' => 'Rejected Cafe']);
        $this->assertDatabaseHas('app_user', ['mobile_number' => '0966666666', 'cafe_id' => null]);

        // The owner can submit a new cafe.
        $this->postJson('/api/v1/cafe/profile', ['name' => 'Second Try'], $this->auth($token))->assertCreated();
    }

    public function test_non_admin_cannot_access_registration_admin_routes(): void
    {
        $cafeToken = $this->cafeToken();

        $this->getJson('/api/v1/cafe-registrations/pending', $this->auth($cafeToken))->assertForbidden();
    }

    protected function registerAndLogin(string $phone): string
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/cafe/register', [
            'name' => 'Owner '.$phone,
            'phone_number' => $phone,
            'password' => 'secret123',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/login', [
            'phone_number' => $phone,
            'password' => 'secret123',
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    /**
     * Bearer headers for $token. Laravel keeps the resolved guard user
     * between requests inside one test, so reset it when switching users.
     */
    protected function auth(string $token): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => "Bearer $token"];
    }

    protected function adminToken(): string
    {
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    protected function cafeToken(): string
    {
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ]);

        $response->assertOk();

        return $response->json('token');
    }
}
