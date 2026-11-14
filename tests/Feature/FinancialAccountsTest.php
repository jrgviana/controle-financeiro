<?php

namespace Tests\Feature;

use App\Livewire\FinancialAccounts;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_accounts(): void
    {
        $this->get('/contas')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_accounts_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/contas')
            ->assertOk()
            ->assertSee('Minhas contas');
    }

    public function test_user_can_create_account_with_brazilian_amount(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openCreate')
            ->set('name', 'Conta principal')
            ->set('type', 'corrente')
            ->set('institution', 'Banco de teste')
            ->set('initialBalance', '1.234,56')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('financial_accounts', [
            'user_id' => $user->id,
            'name' => 'Conta principal',
            'initial_balance' => '1234.56',
            'active' => true,
        ]);
    }

    public function test_user_can_edit_and_disable_own_account_without_deleting_it(): void
    {
        $user = User::factory()->create();
        $account = FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Carteira',
            'type' => 'carteira',
            'initial_balance' => '80.00',
        ]);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('edit', $account->id)
            ->set('name', 'Dinheiro')
            ->set('initialBalance', '120,50')
            ->call('save')
            ->assertHasNoErrors()
            ->call('toggleActive', $account->id);

        $this->assertDatabaseHas('financial_accounts', [
            'id' => $account->id,
            'name' => 'Dinheiro',
            'initial_balance' => '120.50',
            'active' => false,
        ]);
    }

    public function test_user_cannot_view_another_users_accounts(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        FinancialAccount::create([
            'user_id' => $owner->id,
            'name' => 'Conta privada',
            'type' => 'corrente',
            'initial_balance' => '400.00',
        ]);

        Livewire::actingAs($viewer)->test(FinancialAccounts::class)
            ->assertDontSee('Conta privada')
            ->assertSee('0 no total');
    }

    public function test_negative_initial_balance_is_rejected(): void
    {
        Livewire::actingAs(User::factory()->create())->test(FinancialAccounts::class)
            ->call('openCreate')
            ->set('name', 'Teste')
            ->set('initialBalance', '-10,00')
            ->call('save')
            ->assertHasErrors(['initialBalance']);
    }
}
