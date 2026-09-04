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

    public function test_cafe_can_register_with_required_fields(): void
    {
        $response = $this->postJson('/api/v1/cafe/register', [
            'cafe_name' => 'مقهى جديد',
            'phone_number' => '0922222222',
            'password' => 'secret123',
            'address' => 'طرابلس، شارع الرشيد',
            'latitude' => 32.8872,
            'longitude' => 13.1913,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'تم إرسال طلب التسجيل بنجاح، سيتم التواصل معك بعد الموافقة');

        $this->assertDatabaseHas('cafe', [
            'name' => 'مقهى جديد',
            'contact_info' => '0922222222',
            'address' => 'طرابلس، شارع الرشيد',
            'latitude' => 32.8872,
            'longitude' => 13.1913,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('app_user', [
            'mobile_number' => '0922222222',
            'is_active' => false,
        ]);
    }

    public function test_cafe_registration_accepts_optional_email_and_logo(): void
    {
        $logo = UploadedFile::fake()->image('logo.jpg');

        $response = $this->postJson('/api/v1/cafe/register', [
            'cafe_name' => 'مقهى بشعار',
            'phone_number' => '0922222222',
            'email' => 'newcafe@example.com',
            'password' => 'secret123',
            'address' => 'بنغازي',
            'latitude' => 32.0,
            'longitude' => 20.0,
            'logo' => $logo,
        ]);

        $response->assertCreated();

        $cafe = Cafe::where('name', 'مقهى بشعار')->first();
        $this->assertNotNull($cafe->image);
        Storage::disk('public')->assertExists($cafe->image);
        $this->assertDatabaseHas('app_user', [
            'email' => 'newcafe@example.com',
            'mobile_number' => '0922222222',
        ]);
    }

    public function test_unapproved_cafe_cannot_login(): void
    {
        AppUser::create([
            'name' => 'Pending Cafe',
            'email' => 'pending@test.com',
            'mobile_number' => '0933333333',
            'password_hash' => Hash::make('password'),
            'user_type_id' => UserType::where('name', 'cafe')->first()->id,
            'cafe_id' => Cafe::create(['name' => 'Pending', 'is_active' => false])->id,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'phone_number' => '0933333333',
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'الحساب غير نشط، يرجى انتظار موافقة الإدارة');
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
        $pendingCafe = Cafe::create([
            'name' => 'Pending Cafe',
            'is_active' => false,
        ]);

        AppUser::create([
            'name' => 'Pending Owner',
            'mobile_number' => '0944444444',
            'password_hash' => Hash::make('password'),
            'user_type_id' => UserType::where('name', 'cafe')->first()->id,
            'cafe_id' => $pendingCafe->id,
            'is_active' => false,
        ]);

        $token = $this->adminToken();

        $response = $this->getJson('/api/v1/cafe-registrations/pending', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Pending Cafe');
    }

    public function test_admin_can_approve_cafe_registration(): void
    {
        $pendingCafe = Cafe::create([
            'name' => 'Pending Cafe',
            'is_active' => false,
        ]);

        $pendingUser = AppUser::create([
            'name' => 'Pending Owner',
            'mobile_number' => '0955555555',
            'password_hash' => Hash::make('password'),
            'user_type_id' => UserType::where('name', 'cafe')->first()->id,
            'cafe_id' => $pendingCafe->id,
            'is_active' => false,
        ]);

        $token = $this->adminToken();

        $this->postJson("/api/v1/cafe-registrations/{$pendingCafe->id}/approve", [], [
            'Authorization' => "Bearer $token",
        ])->assertOk()
            ->assertJsonPath('message', 'تمت الموافقة على الطلب بنجاح');

        $this->assertTrue($pendingCafe->fresh()->is_active);
        $this->assertTrue($pendingUser->fresh()->is_active);

        $this->postJson('/api/v1/login', [
            'phone_number' => '0955555555',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_admin_can_reject_cafe_registration(): void
    {
        $pendingCafe = Cafe::create([
            'name' => 'Rejected Cafe',
            'is_active' => false,
        ]);

        AppUser::create([
            'name' => 'Rejected Owner',
            'mobile_number' => '0966666666',
            'password_hash' => Hash::make('password'),
            'user_type_id' => UserType::where('name', 'cafe')->first()->id,
            'cafe_id' => $pendingCafe->id,
            'is_active' => false,
        ]);

        $token = $this->adminToken();

        $this->postJson("/api/v1/cafe-registrations/{$pendingCafe->id}/reject", [], [
            'Authorization' => "Bearer $token",
        ])->assertOk()
            ->assertJsonPath('message', 'تم رفض الطلب بنجاح');

        $this->assertDatabaseMissing('cafe', ['name' => 'Rejected Cafe']);
        $this->assertDatabaseMissing('app_user', ['mobile_number' => '0966666666']);
    }

    public function test_non_admin_cannot_access_registration_admin_routes(): void
    {
        $cafeToken = $this->cafeToken();

        $this->getJson('/api/v1/cafe-registrations/pending', [
            'Authorization' => "Bearer $cafeToken",
        ])->assertForbidden();
    }

    public function test_registration_requires_mandatory_fields(): void
    {
        $this->postJson('/api/v1/cafe/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cafe_name', 'phone_number', 'password', 'address'])
            ->assertJsonMissingValidationErrors(['latitude', 'longitude']);
    }

    public function test_cafe_can_register_without_location(): void
    {
        $response = $this->postJson('/api/v1/cafe/register', [
            'cafe_name' => 'مقهى بلا موقع',
            'phone_number' => '0922222223',
            'password' => 'secret123',
            'address' => 'طرابلس',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('cafe', [
            'name' => 'مقهى بلا موقع',
            'address' => 'طرابلس',
            'latitude' => null,
            'longitude' => null,
            'is_active' => false,
        ]);
    }

    public function test_auto_approve_creates_active_cafe_and_allows_login(): void
    {
        PremiumFeature::updateOrCreate(
            ['code' => 'cafe_auto_approve'],
            ['name' => 'تفعيل تلقائي للمقاهي', 'is_active' => true]
        );

        $response = $this->postJson('/api/v1/cafe/register', [
            'cafe_name' => 'مقهى تلقائي',
            'phone_number' => '0922222224',
            'password' => 'secret123',
            'address' => 'بنغازي',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'تم تسجيل مقهاك وتفعيله، يمكنك تسجيل الدخول الآن');

        $this->assertDatabaseHas('cafe', [
            'name' => 'مقهى تلقائي',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('app_user', [
            'mobile_number' => '0922222224',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/login', [
            'phone_number' => '0922222224',
            'password' => 'secret123',
        ])->assertOk();
    }

    protected function adminToken(): string
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    protected function cafeToken(): string
    {
        $response = $this->postJson('/api/v1/login', [
            'phone_number' => '0911111111',
            'password' => 'password',
        ]);

        $response->assertOk();

        return $response->json('token');
    }
}
