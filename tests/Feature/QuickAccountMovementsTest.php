<?php

namespace Tests\Feature;

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

class QuickAccountMovementsTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $name, string $amount = '0.00', string $type = 'corrente'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'initial_balance' => $amount,
            'active' => true,
        ]);
    }

    private function income(User $user, FinancialAccount $account, string $value): void
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

    private function expense(User $user, FinancialAccount $account, string $value): void
    {
        DailyExpense::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'paid_on' => now()->toDateString(),
            'description' => 'Pagamento efetuado',
            'category' => 'outros',
            'amount' => $value,
        ]);
    }

    public function test_accounts_list_does_not_force_horizontal_scrolling(): void
    {
        $user = User::factory()->create();
        $this->account($user, 'Itaú', '250.00');

        $response = $this->actingAs($user)->get('/contas');

        $response->assertOk()
            ->assertSee('Contas cadastradas')
            ->assertSee('Entradas')
            ->assertSee('Saídas')
            ->assertSee('Disponível')
            ->assertDontSee('overflow-x-auto')
            ->assertDontSee('min-w-[1460px]')
            ->assertSee('Transferir / Sacar');
    }

    public function test_modal_opens_for_account_with_available_balance(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '200.00');
        $this->account($user, 'Neon');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->assertHasNoErrors()
            ->assertSet('transferModalOpen', true)
            ->assertSet('transferSourceId', $itau->id)
            ->assertSee('Movimentar saldo')
            ->assertSee('Disponível: R$ 200,00');
    }

    public function test_regular_transfer_moves_only_money_left_after_paid_expenses(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '100.00');
        $neon = $this->account($user, 'Neon');
        $this->income($user, $itau, '1000.00');
        $this->expense($user, $itau, '600.00');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->set('transferTargetId', (string) $neon->id)
            ->set('transferAmount', 'R$ 400,00')
            ->call('saveTransfer')
            ->assertHasNoErrors()
            ->assertSet('transferModalOpen', false)
            ->assertSee('Transferência registrada com sucesso');

        $this->assertDatabaseHas('account_transfers', [
            'from_account_id' => $itau->id,
            'to_account_id' => $neon->id,
            'amount' => '400.00',
        ]);

        $calculator = new FinancialBalance();
        $this->assertSame(10000, $calculator->currentCents($itau));
        $this->assertSame(40000, $calculator->currentCents($neon));
        $this->assertSame(50000, $calculator->currentCents($itau) + $calculator->currentCents($neon));
        $this->assertDatabaseCount('received_incomes', 1);
        $this->assertDatabaseCount('daily_expenses', 1);
    }

    public function test_modal_cannot_send_more_than_available_balance(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '100.00');
        $neon = $this->account($user, 'Neon');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->set('transferTargetId', (string) $neon->id)
            ->set('transferAmount', 'R$ 100,01')
            ->call('saveTransfer')
            ->assertHasErrors(['transferAmount']);

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_withdrawal_moves_money_to_wallet_with_saque_note(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '300.00');
        $wallet = $this->account($user, 'Carteira', '20.00', 'carteira');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->set('transferMode', 'withdrawal')
            ->assertSet('transferTargetId', (string) $wallet->id)
            ->set('transferAmount', 'R$ 80,00')
            ->set('transferNotes', 'Caixa eletrônico')
            ->call('saveTransfer')
            ->assertHasNoErrors()
            ->assertSee('Saque registrado');

        $this->assertDatabaseHas('account_transfers', [
            'from_account_id' => $itau->id,
            'to_account_id' => $wallet->id,
            'amount' => '80.00',
            'notes' => 'Saque em espécie — Caixa eletrônico',
        ]);

        $calculator = new FinancialBalance();
        $this->assertSame(22000, $calculator->currentCents($itau));
        $this->assertSame(10000, $calculator->currentCents($wallet));
        $this->assertSame(32000, $calculator->currentCents($itau) + $calculator->currentCents($wallet));
    }

    public function test_withdrawal_requires_an_active_wallet(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '100.00');
        $this->account($user, 'Neon');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->set('transferMode', 'withdrawal')
            ->assertSet('transferTargetId', '')
            ->assertSee('Cadastre uma conta do tipo Carteira')
            ->set('transferAmount', 'R$ 30,00')
            ->call('saveTransfer')
            ->assertHasErrors(['transferTargetId']);

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_withdrawal_cannot_be_redirected_to_regular_bank_account(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '150.00');
        $neon = $this->account($user, 'Neon');
        $this->account($user, 'Carteira', '0.00', 'carteira');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->set('transferMode', 'withdrawal')
            ->set('transferTargetId', (string) $neon->id)
            ->set('transferAmount', 'R$ 40,00')
            ->call('saveTransfer')
            ->assertHasErrors(['transferTargetId']);

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_no_transfer_action_when_account_has_no_balance(): void
    {
        $user = User::factory()->create();
        $empty = $this->account($user, 'Itaú', '0.00');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertDontSee('Transferir / Sacar')
            ->call('openTransfer', $empty->id)
            ->assertHasErrors(['transferSourceId'])
            ->assertSet('transferModalOpen', false);
    }

    public function test_cannot_use_other_users_account_as_destination(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $source = $this->account($user, 'Itaú', '100.00');
        $this->account($user, 'Minha Carteira', '0.00', 'carteira');
        $foreign = $this->account($owner, 'Conta privada');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $source->id)
            ->set('transferTargetId', (string) $foreign->id)
            ->set('transferAmount', 'R$ 5,00')
            ->call('saveTransfer')
            ->assertHasErrors(['transferTargetId']);

        $this->assertDatabaseCount('account_transfers', 0);
    }

    public function test_cancel_does_not_create_transfer(): void
    {
        $user = User::factory()->create();
        $itau = $this->account($user, 'Itaú', '200.00');
        $this->account($user, 'Neon');

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openTransfer', $itau->id)
            ->set('transferAmount', 'R$ 25,00')
            ->call('closeTransfer')
            ->assertSet('transferModalOpen', false)
            ->assertSet('transferAmount', 'R$ 0,00');

        $this->assertDatabaseCount('account_transfers', 0);
    }
}
