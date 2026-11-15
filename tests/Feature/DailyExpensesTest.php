<?php

namespace Tests\Feature;

use App\Livewire\DailyExpenses;
use App\Livewire\FinancialAccounts;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $name = 'Carteira', string $initial = '100.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'carteira',
            'initial_balance' => $initial,
            'active' => true,
        ]);
    }

    private function expense(User $user, FinancialAccount $account, string $amount = '25.00'): DailyExpense
    {
        return DailyExpense::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'paid_on' => now()->toDateString(),
            'description' => 'Almoço',
            'category' => 'alimentacao',
            'amount' => $amount,
        ]);
    }

    public function test_guest_cannot_open_expenses(): void
    {
        $this->get('/gastos')->assertRedirect('/login');
    }

    public function test_logged_in_user_can_open_expenses(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/gastos')->assertOk()->assertSee('Gastos diários');
    }

    public function test_user_can_register_paid_expense_with_money_mask(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->call('openCreate')
            ->set('paidOn', now()->toDateString())
            ->set('description', 'Combustível')
            ->set('category', 'transporte')
            ->set('accountId', (string) $account->id)
            ->set('amount', 'R$ 12,50')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('daily_expenses', [
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'description' => 'Combustível',
            'category' => 'transporte',
            'amount' => '12.50',
        ]);
    }

    public function test_paid_expense_can_be_edited_and_soft_deleted(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $expense = $this->expense($user, $account);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->call('edit', $expense->id)
            ->set('description', 'Almoço de trabalho')
            ->set('amount', 'R$ 30,00')
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete', $expense->id);

        $this->assertSoftDeleted('daily_expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('daily_expenses', [
            'id' => $expense->id,
            'amount' => '30.00',
            'description' => 'Almoço de trabalho',
        ]);
    }

    public function test_future_payment_date_is_not_allowed(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->call('openCreate')
            ->set('description', 'Conta futura')
            ->set('accountId', (string) $account->id)
            ->set('amount', 'R$ 15,00')
            ->set('paidOn', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['paidOn']);
    }

    public function test_zero_payment_is_not_allowed(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->call('openCreate')
            ->set('description', 'Teste')
            ->set('accountId', (string) $account->id)
            ->set('amount', 'R$ 0,00')
            ->call('save')
            ->assertHasErrors(['amount']);
    }

    public function test_user_cannot_register_spending_on_another_users_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->account($other);
        $otherAccount = $this->account($owner);

        Livewire::actingAs($other)->test(DailyExpenses::class)
            ->call('openCreate')
            ->set('description', 'Gasto indevido')
            ->set('accountId', (string) $otherAccount->id)
            ->set('amount', 'R$ 5,00')
            ->call('save')
            ->assertHasErrors(['accountId']);

        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_other_users_expenses_are_not_visible(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $this->expense($owner, $this->account($owner));

        Livewire::actingAs($viewer)->test(DailyExpenses::class)
            ->assertDontSee('Almoço')
            ->assertSee('Nenhum gasto neste período');
    }

    public function test_spending_changes_estimated_account_balance_without_changing_initial_balance(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 'Conta principal', '100.00');
        $this->expense($user, $account, '25.00');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('R$ 100,00')
            ->assertSee('R$ 25,00')
            ->assertSee('R$ 75,00');

        $this->assertDatabaseHas('financial_accounts', [
            'id' => $account->id,
            'initial_balance' => '100.00',
        ]);
    }

    public function test_deficit_is_shown_as_amount_to_cover(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 'Carteira', '10.00');
        $this->expense($user, $account, '25.00');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('Faltam R$')
            ->assertSee('15,00');
    }
}
