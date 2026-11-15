<?php

namespace Tests\Feature;

use App\Livewire\AccountTransfers;
use App\Livewire\FinancialAccounts;
use App\Models\AccountTransfer;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ReceivedIncome;
use App\Models\User;
use App\Support\FinancialBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountTransfersTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $name, string $initial = '0.00', bool $active = true): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'corrente',
            'initial_balance' => $initial,
            'active' => $active,
        ]);
    }

    private function receive(User $user, FinancialAccount $account, string $value): void
    {
        ReceivedIncome::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'received_on' => now()->toDateString(),
            'description' => 'Salário recebido',
            'category' => 'salario',
            'amount' => $value,
        ]);
    }

    private function spend(User $user, FinancialAccount $account, string $value): void
    {
        DailyExpense::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'paid_on' => now()->toDateString(),
            'description' => 'Conta paga',
            'category' => 'moradia',
            'amount' => $value,
        ]);
    }

    private function send(User $user, FinancialAccount $from, FinancialAccount $to, string $value): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($user)->test(AccountTransfers::class)
            ->set('fromAccountId', (string) $from->id)
            ->set('toAccountId', (string) $to->id)
            ->set('amount', $value)
            ->call('save');
    }

    public function test_guest_cannot_open_transfers(): void
    {
        $this->get('/transferencias')->assertRedirect('/login');
    }

    public function test_form_is_ready_without_creating_any_transfer(): void
    {
        $user = User::factory()->create();
        $this->account($user, 'Itaú', '300.00');
        $this->account($user, 'Neon', '0.00');

        Livewire::actingAs($user)->test(AccountTransfers::class)
            ->assertSee('Nova transferência')
            ->assertSee('Disponível para transferir: R$')
            ->assertSee('300,00')
            ->assertSet('amount', 'R$ 0,00');

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_transfer_uses_only_available_balance_after_receipts_and_payments(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '100.00');
        $neon = $this->account($user, 'Neon');
        $this->receive($user, $itau, '1000.00');
        $this->spend($user, $itau, '600.00');

        $this->send($user, $itau, $neon, 'R$ 400,00')
            ->assertHasNoErrors()
            ->assertSet('amount', 'R$ 0,00')
            ->assertSee('Itaú')
            ->assertSee('Neon');

        $this->assertDatabaseHas('account_transfers', [
            'user_id' => $user->id,
            'from_account_id' => $itau->id,
            'to_account_id' => $neon->id,
            'amount' => '400.00',
        ]);

        $balance = new FinancialBalance();
        $this->assertSame(10000, $balance->currentCents($itau));
        $this->assertSame(40000, $balance->currentCents($neon));

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('R$ 100,00')
            ->assertSee('R$ 400,00')
            ->assertSee('R$ 500,00');

        $this->assertDatabaseCount('received_incomes', 1);
        $this->assertDatabaseCount('daily_expenses', 1);
    }

    public function test_transfer_does_not_change_the_combined_total_or_create_fake_income(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '1000.00');
        $b = $this->account($user, 'Conta B', '500.00');

        $this->send($user, $a, $b, 'R$ 300,00')->assertHasNoErrors();

        $calculator = new FinancialBalance();
        $this->assertSame(70000, $calculator->currentCents($a));
        $this->assertSame(80000, $calculator->currentCents($b));
        $this->assertSame(150000, $calculator->currentCents($a) + $calculator->currentCents($b));
        $this->assertDatabaseCount('received_incomes', 0);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_transfer_above_balance_is_rejected_without_creating_a_record(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '100.00');
        $b = $this->account($user, 'Conta B');

        $this->send($user, $a, $b, 'R$ 100,01')->assertHasErrors(['amount']);

        $this->assertDatabaseCount('account_transfers', 0);
        $this->assertSame(10000, (new FinancialBalance())->currentCents($a));
    }

    public function test_second_transfer_is_rejected_when_first_used_remaining_balance(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '100.00');
        $b = $this->account($user, 'Conta B');

        $this->send($user, $a, $b, 'R$ 90,00')->assertHasNoErrors();
        $this->send($user, $a, $b, 'R$ 11,00')->assertHasErrors(['amount']);

        $this->assertDatabaseCount('account_transfers', 1);
    }

    public function test_cannot_transfer_between_same_account(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '100.00');
        $this->account($user, 'Conta B');

        $this->send($user, $a, $a, 'R$ 10,00')->assertHasErrors(['toAccountId']);
        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_cannot_transfer_using_other_users_accounts(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $secret = $this->account($owner, 'Banco secreto', '500.00');
        $viewerAccount = $this->account($viewer, 'Conta pessoal', '150.00');
        $this->account($viewer, 'Conta extra', '0.00');

        $this->send($viewer, $secret, $viewerAccount, 'R$ 30,00')->assertHasErrors(['fromAccountId']);
        $this->send($viewer, $viewerAccount, $secret, 'R$ 30,00')->assertHasErrors(['toAccountId']);

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_inactive_accounts_cannot_be_transfer_origins_or_destinations(): void
    {
        $user = User::factory()->create();
        $active = $this->account($user, 'Conta ativa', '150.00');
        $inactive = $this->account($user, 'Conta inativa', '0.00', false);

        $this->send($user, $active, $inactive, 'R$ 50,00')->assertHasErrors(['toAccountId']);
        $this->send($user, $inactive, $active, 'R$ 50,00')->assertHasErrors(['fromAccountId']);
        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_future_transfer_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '100.00');
        $b = $this->account($user, 'Conta B');

        Livewire::actingAs($user)->test(AccountTransfers::class)
            ->set('fromAccountId', (string) $a->id)
            ->set('toAccountId', (string) $b->id)
            ->set('amount', 'R$ 10,00')
            ->set('transferredOn', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['transferredOn']);

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_undo_preserves_history_and_restores_balances(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '200.00');
        $b = $this->account($user, 'Conta B');

        $this->send($user, $a, $b, 'R$ 80,00')->assertHasNoErrors();
        $transfer = AccountTransfer::firstOrFail();

        Livewire::actingAs($user)->test(AccountTransfers::class)
            ->call('undo', $transfer->id)
            ->assertHasNoErrors()
            ->assertSee('Estornada');

        $this->assertSoftDeleted('account_transfers', ['id' => $transfer->id]);
        $this->assertDatabaseHas('account_transfers', ['id' => $transfer->id, 'amount' => '80.00']);
        $calculator = new FinancialBalance();
        $this->assertSame(20000, $calculator->currentCents($a));
        $this->assertSame(0, $calculator->currentCents($b));
    }

    public function test_undo_refuses_when_destination_has_spent_the_received_money(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, 'Conta A', '200.00');
        $b = $this->account($user, 'Conta B');

        $this->send($user, $a, $b, 'R$ 80,00')->assertHasNoErrors();
        $transfer = AccountTransfer::firstOrFail();
        $this->spend($user, $b, '50.00');

        Livewire::actingAs($user)->test(AccountTransfers::class)
            ->call('undo', $transfer->id)
            ->assertHasErrors(['reversal']);

        $this->assertFalse(AccountTransfer::findOrFail($transfer->id)->trashed());
        $this->assertSame(3000, (new FinancialBalance())->currentCents($b));
    }

    public function test_user_cannot_undo_another_users_transfer(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $a = $this->account($owner, 'Conta A', '100.00');
        $b = $this->account($owner, 'Conta B');
        $this->send($owner, $a, $b, 'R$ 50,00')->assertHasNoErrors();

        $transfer = AccountTransfer::firstOrFail();
        Livewire::actingAs($viewer)->test(AccountTransfers::class)
            ->call('undo', $transfer->id)
            ->assertNotFound();

        $this->assertNotNull(AccountTransfer::find($transfer->id));
    }
}
