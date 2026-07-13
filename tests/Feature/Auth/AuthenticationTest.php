<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\EnforceCsrfForTest;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_login_and_see_role_dashboard(): void
    {
        $user = $this->createUserWithDashboardRole('owner', [
            'email' => 'owner@example.test',
            'username' => 'owner_local',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'login' => 'owner@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Owner')
            ->assertSee('Ringkasan Bisnis');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    #[Test]
    public function user_can_login_with_username(): void
    {
        $this->createUserWithDashboardRole('owner', [
            'username' => 'owner_username',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'login' => 'owner_username',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    #[Test]
    public function remember_me_sets_recaller_cookie(): void
    {
        $this->createUserWithDashboardRole('owner', [
            'email' => 'remember@example.test',
            'password' => 'password',
        ]);

        $response = $this->post(route('login.store'), [
            'login' => 'remember@example.test',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    #[Test]
    public function invalid_credentials_are_rejected_in_indonesian(): void
    {
        $this->post(route('login.store'), [
            'login' => 'unknown@example.test',
            'password' => 'salah-password',
        ])->assertSessionHasErrors(['login' => 'Email/username atau kata sandi tidak sesuai.']);

        $this->assertGuest();
    }

    #[Test]
    public function inactive_account_is_rejected_with_clear_message(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'username' => 'inactive_user',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'login' => 'inactive_user',
            'password' => 'password',
        ])->assertSessionHasErrors(['login' => 'Akun Anda tidak aktif. Hubungi administrator untuk membuka akses.']);

        $this->assertGuest();
    }

    #[Test]
    public function login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'login' => 'limited@example.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.store'), [
            'login' => 'limited@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['login']);

        $this->assertStringContainsString(
            'Terlalu banyak percobaan login.',
            session('errors')->first('login')
        );
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[DataProvider('roleDashboardProvider')]
    #[Test]
    public function dashboard_placeholder_matches_the_user_role(string $roleName, string $expectedText): void
    {
        $user = $this->createUserWithDashboardRole($roleName);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee($expectedText);
    }

    #[Test]
    public function user_without_dashboard_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    #[Test]
    public function logout_invalidates_authenticated_session(): void
    {
        $user = $this->createUserWithDashboardRole('owner');

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function reset_password_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);

        $this->post(route('password.email'), [
            'email' => 'reset@example.test',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    #[Test]
    public function password_can_be_reset_with_valid_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'reset@example.test',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class);
    }

    #[Test]
    public function profile_and_password_can_be_updated(): void
    {
        $user = $this->createUserWithDashboardRole('owner', [
            'email' => 'profile@example.test',
            'username' => 'profile_user',
            'password' => 'password',
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'username' => 'nama_baru',
            'email' => 'new-profile@example.test',
            'phone_number' => '6281234567890',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru',
            'username' => 'nama_baru',
            'email' => 'new-profile@example.test',
            'phone_number' => '6281234567890',
        ]);

        $this->actingAs($user->fresh())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    #[Test]
    public function password_update_requires_recent_confirmation(): void
    {
        $user = $this->createUserWithDashboardRole('owner');

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function csrf_protection_rejects_login_without_token_when_middleware_is_enabled(): void
    {
        Route::post('/_test/csrf-probe', fn () => response('ok'))
            ->middleware(['web', EnforceCsrfForTest::class]);

        $this->post('/_test/csrf-probe')->assertStatus(419);
    }

    /** @return array<string, array{string, string}> */
    public static function roleDashboardProvider(): array
    {
        return [
            'owner' => ['owner', 'Dashboard Owner'],
            'warehouse' => ['kepala_gudang', 'Dashboard Gudang'],
            'retail' => ['kepala_toko', 'Dashboard Retail'],
            'super admin' => ['super_admin', 'Dashboard Super Admin'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUserWithDashboardRole(string $roleName, array $attributes = []): User
    {
        $permission = Permission::findOrCreate('dashboard.view');
        $role = Role::findOrCreate($roleName);
        $role->givePermissionTo($permission);
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
