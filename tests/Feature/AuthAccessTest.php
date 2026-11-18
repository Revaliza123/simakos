<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_a_protected_page(): void
    {
        $this->get(route('overview'))
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk ke SIMAKOS');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Buat akun penghuni');
    }

    public function test_public_registration_creates_and_authenticates_a_tenant(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Andi Penghuni',
            'email' => 'andi@example.com',
            'password' => 'password-aman',
            'password_confirmation' => 'password-aman',
            'role' => 'admin',
        ]);

        $user = User::where('email', 'andi@example.com')->firstOrFail();

        $this->assertSame('tenant', $user->role);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('tenant.overview'));
    }

    public function test_login_redirects_each_role_to_its_own_portal(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password-aman',
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password-aman',
        ])->assertRedirect(route('overview'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('overview'))->assertOk()->assertSee('admin@example.com');

        auth()->logout();

        $tenant = User::factory()->create([
            'email' => 'tenant@example.com',
            'password' => 'password-aman',
            'role' => 'tenant',
        ]);

        $this->post(route('login.store'), [
            'email' => $tenant->email,
            'password' => 'password-aman',
        ])->assertRedirect(route('tenant.overview'));

        $this->assertAuthenticatedAs($tenant);
        $this->get(route('tenant.overview'))->assertOk()->assertSee('tenant@example.com');
    }

    public function test_roles_cannot_access_the_other_role_portal(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)->get(route('overview'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('tenant.overview'))->assertForbidden();
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
