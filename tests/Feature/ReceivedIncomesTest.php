<?php

namespace Tests\Feature;

use App\Livewire\FinancialAccounts;
use App\Livewire\ReceivedIncomes;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ReceivedIncome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivedIncomesTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $name = 'Conta principal', string $initial = '100.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'corrente',
            'initial_balance' => $initial,
            'active' => true,
        ]);
    }

    private function income(User $user, FinancialAccount $account, string $amount = '120.00'): ReceivedIncome
    {
        return ReceivedIncome::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'received_on' => now()->toDateString(),
            'description' => 'Salário',
            'category' => 'salario',
            'amount' => $amount,
        ]);
    }

    public function test_guests_cannot_access_receivables(): void
    {
        $this->get('/recebimentos')->assertRedirect('/login');
    }

    public function test_form_is_visible_with_no_income_automatically_generated(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        Livewire::actingAs($user)->test(ReceivedIncomes::class)
            ->assertSee('Registrar dinheiro recebido')
            ->assertSee('Data em que recebeu')
            ->assertSee('Nenhum recebimento neste período')
            ->assertSet('accountId', (string) $account->id)
            ->assertSet('amount', 'R$ 0,00');

        $this->assertDatabaseCount('received_incomes', 0);
    }

    public function test_salary_is_credited_only_after_saving_a_confirmed_receipt(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 'Neon', '100.00');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('R$ 100,00');

        Livewire::actingAs($user)->test(ReceivedIncomes::class)
            ->set('description', 'Salário novembro')
            ->set('category', 'salario')
            ->set('accountId', (string) $account->id)
            ->set('receivedOn', now()->toDateString())
            ->set('amount', 'R$ 1.250,00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editingId', null)
            ->assertSet('description', '')
            ->assertSet('amount', 'R$ 0,00')
            ->assertSet('accountId', (string) $account->id)
            ->assertSee('Salário novembro');

        $this->assertDatabaseHas('received_incomes', [
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'category' => 'salario',
            'amount' => '1250.00',
        ]);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('R$ 1.350,00')
            ->assertSee('R$ 1.250,00');

        $this->assertDatabaseHas('financial_accounts', [
            'id' => $account->id,
            'initial_balance' => '100.00',
        ]);
    }

    public function test_balance_considers_initial_balance_income_and_paid_expenses(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 'Banco de teste', '100.00');
        $this->income($user, $account, '200.00');

        DailyExpense::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'paid_on' => now()->toDateString(),
            'description' => 'Combustível',
            'category' => 'transporte',
            'amount' => '80.00',
        ]);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('R$ 100,00')
            ->assertSee('R$ 200,00')
            ->assertSee('R$ 80,00')
            ->assertSee('R$ 220,00');
    }

    public function test_receipt_cannot_be_registered_for_a_future_date(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        Livewire::actingAs($user)->test(ReceivedIncomes::class)
            ->set('description', 'Salário previsto')
            ->set('accountId', (string) $account->id)
            ->set('receivedOn', now()->addDay()->toDateString())
            ->set('amount', 'R$ 3.000,00')
            ->call('save')
            ->assertHasErrors(['receivedOn']);

        $this->assertDatabaseCount('received_incomes', 0);
    }

    public function test_zero_amount_cannot_be_received(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        Livewire::actingAs($user)->test(ReceivedIncomes::class)
            ->set('description', 'Salário')
            ->set('accountId', (string) $account->id)
            ->set('amount', 'R$ 0,00')
            ->call('save')
            ->assertHasErrors(['amount']);
    }

    public function test_user_cannot_use_account_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $account = $this->account($owner);
        $this->account($viewer, 'Conta secundária');

        Livewire::actingAs($viewer)->test(ReceivedIncomes::class)
            ->set('description', 'Recebimento indevido')
            ->set('accountId', (string) $account->id)
            ->set('amount', 'R$ 50,00')
            ->call('save')
            ->assertHasErrors(['accountId']);

        $this->assertDatabaseCount('received_incomes', 0);
    }

    public function test_user_only_sees_their_own_receipts(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $this->income($owner, $this->account($owner));

        Livewire::actingAs($viewer)->test(ReceivedIncomes::class)
            ->assertDontSee('Salário')
            ->assertSee('Nenhum recebimento neste período');
    }

    public function test_user_can_edit_and_remove_a_receipt_without_erasing_the_audit_record(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $income = $this->income($user, $account);

        Livewire::actingAs($user)->test(ReceivedIncomes::class)
            ->call('edit', $income->id)
            ->assertSet('editingId', $income->id)
            ->set('description', 'Renda extra')
            ->set('category', 'extra')
            ->set('amount', 'R$ 90,50')
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete', $income->id);

        $this->assertSoftDeleted('received_incomes', ['id' => $income->id]);
        $this->assertDatabaseHas('received_incomes', [
            'id' => $income->id,
            'description' => 'Renda extra',
            'amount' => '90.50',
        ]);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('R$ 100,00');
    }
}
