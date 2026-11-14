<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_screen_loads(): void
    {
        $this->get('/login')->assertOk()->assertSee('Entre na sua conta');
    }

    public function test_user_can_log_in_with_numeric_pin(): void
    {
        $user = User::factory()->create([
            'username' => 'usuario',
            'password' => Hash::make('548231'),
        ]);

        $this->post('/login', [
            'username' => 'usuario',
            'pin' => '548231',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_incorrect_pin_is_rejected(): void
    {
        User::factory()->create([
            'username' => 'usuario',
            'password' => Hash::make('548231'),
        ]);

        $this->post('/login', [
            'username' => 'usuario',
            'pin' => '111111',
        ])->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_must_have_six_digits(): void
    {
        $this->post('/login', [
            'username' => 'usuario',
            'pin' => '1234',
        ])->assertSessionHasErrors('pin');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create(['username' => 'usuario']);

        $this->actingAs($user)->post('/sair')->assertRedirect('/login');
        $this->assertGuest();
    }
}
