<?php

namespace Tests\Feature;

use App\Livewire\CreditCards;
use App\Livewire\DailyExpenses;
use App\Livewire\FinancialAccounts;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ScheduledBill;
use App\Models\User;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduledBillsTest extends TestCase
{
    use RefreshDatabase;

    private function bank(User $user, string $amount = '1000.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Itaú',
            'type' => 'corrente',
            'initial_balance' => $amount,
            'active' => true,
        ]);
    }

    private function card(User $user): CreditCard
    {
        return CreditCard::create([
            'user_id' => $user->id,
            'name' => 'Mastercard',
            'closing_day' => 10,
            'due_day' => 15,
            'active' => true,
        ]);
    }

    private function createBill(User $user, string $amount = 'R$ 135,70'): void
    {
        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openBillForm')
            ->set('billDescription', 'Conta de energia')
            ->set('billCategory', 'moradia')
            ->set('billAmount', $amount)
            ->set('billDueOn', '2026-12-10')
            ->call('saveBill')
            ->assertHasNoErrors();
    }

    public function test_guest_cannot_access_accounts_payables(): void
    {
        $this->get('/contas')->assertRedirect('/login');
    }

    public function test_future_bill_is_not_subtracted_until_paid_and_is_not_income_or_transfer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $this->createBill($user);

        $this->assertDatabaseHas('scheduled_bills', [
            'user_id' => $user->id,
            'description' => 'Conta de energia',
            'amount' => '135.70',
            'daily_expense_id' => null,
        ]);
        $this->assertSame('2026-12-10', ScheduledBill::firstOrFail()->due_on->toDateString());
        $this->assertSame(100000, (new FinancialBalance())->currentCents($account));
        $this->assertDatabaseCount('daily_expenses', 0);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('Saldo disponível calculado')
            ->assertSee('R$ 1.000,00')
            ->assertSee('Contas comuns pendentes')
            ->assertSee('R$ 135,70')
            ->assertSee('Conta de energia');
    }

    public function test_bill_payment_creates_exactly_one_expense_and_prevents_double_payment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $this->createBill($user);
        $bill = ScheduledBill::firstOrFail();

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openBillPayment', $bill->id)
            ->set('billPaymentAccountId', (string) $account->id)
            ->set('billPaidOn', '2026-11-15')
            ->call('confirmBillPayment')
            ->assertHasNoErrors()
            ->assertSee('Conta quitada');

        $bill->refresh();
        $this->assertNotNull($bill->daily_expense_id);
        $this->assertDatabaseCount('daily_expenses', 1);
        $this->assertDatabaseHas('daily_expenses', [
            'financial_account_id' => $account->id, 'amount' => '135.70',
        ]);
        $this->assertSame(86430, (new FinancialBalance())->currentCents($account));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\PayScheduledBill::class)
            ->execute($user->id, $bill->id, $account->id, '2026-11-15');
    }

    public function test_insufficient_bank_balance_prevents_bill_payment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user, '30.00');
        $this->createBill($user);
        $bill = ScheduledBill::firstOrFail();

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openBillPayment', $bill->id)
            ->set('billPaymentAccountId', (string) $account->id)
            ->call('confirmBillPayment')
            ->assertHasErrors(['billPaymentAccountId']);

        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertNull($bill->fresh()->daily_expense_id);
    }

    public function test_paid_scheduled_expense_cannot_be_edited_or_deleted_from_gastos(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $this->createBill($user);
        $bill = ScheduledBill::firstOrFail();
        app(\App\Services\PayScheduledBill::class)->execute($user->id, $bill->id, $account->id, '2026-11-15');

        $expense = DailyExpense::firstOrFail();
        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->call('edit', $expense->id)
            ->assertSet('editingId', null)
            ->call('delete', $expense->id)
            ->assertSee('vinculado');

        $this->assertDatabaseHas('daily_expenses', ['id' => $expense->id, 'deleted_at' => null]);
    }

    public function test_bill_is_scoped_to_user(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $this->createBill($owner);
        $bill = ScheduledBill::firstOrFail();
        $this->bank($viewer);

        Livewire::actingAs($viewer)->test(FinancialAccounts::class)
            ->assertDontSee('Conta de energia')
            ->call('openBillPayment', $bill->id)
            ->assertStatus(404);
    }

    public function test_card_invoice_payment_is_available_in_accounts_and_not_on_card_consultation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $card = $this->card($user);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', 'Curso')
            ->set('amount', 'R$ 300,00')
            ->set('paidOn', '2026-11-11')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();

        $this->actingAs($user)->get('/cartoes')
            ->assertOk()
            ->assertDontSee('wire:submit="payInvoice"', false)
            ->assertDontSee('wire:submit="addInterest"', false);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->assertSee('Faturas de cartão a vencer')
            ->assertSee('Mastercard')
            ->call('openCardPayment', $invoice->id)
            ->set('cardPaymentAccountId', (string) $account->id)
            ->set('cardPaymentAmount', 'R$ 100,00')
            ->call('confirmCardPayment')
            ->assertHasNoErrors()
            ->assertSee('Pagamento parcial confirmado');

        $this->assertSame(90000, (new FinancialBalance())->currentCents($account));
        $this->assertSame(20000, (new CreditCardLedger())->totalDebt($card));
        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_manual_card_interest_is_added_only_when_explicitly_recorded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->bank($user);
        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', 'Mercado')
            ->set('amount', 'R$ 200,00')
            ->set('paidOn', '2026-11-11')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();
        $this->assertDatabaseCount('credit_card_invoice_charges', 0);

        Livewire::actingAs($user)->test(FinancialAccounts::class)
            ->call('openCardPayment', $invoice->id)
            ->set('cardInterestDescription', 'Juros informados')
            ->set('cardInterestAmount', 'R$ 18,50')
            ->call('addCardInterest')
            ->assertHasNoErrors();

        $this->assertSame(21850, (new CreditCardLedger())->totalDebt($card));
        $this->assertDatabaseCount('credit_card_invoice_charges', 1);
    }

    public function test_future_installments_do_not_carry_as_overdue_balance_before_due_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->bank($user);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', 'Bomba hidráulica')
            ->set('amount', 'R$ 1.250,00')
            ->set('installmentsCount', 10)
            ->set('paidOn', '2026-11-14')
            ->call('save')
            ->assertHasNoErrors();

        $rows = (new CreditCardLedger())->invoices($card);

        $this->assertCount(10, $rows);
        foreach ($rows as $row) {
            $this->assertSame(0, $row['carry_cents']);
            $this->assertSame(12500, $row['remaining_cents']);
        }
        $this->assertSame(125000, (new CreditCardLedger())->totalDebt($card));
    }

    public function test_card_listing_has_modal_and_no_invoice_details_when_closed(): void
    {
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->actingAs($user)->get('/cartoes')
            ->assertOk()
            ->assertDontSee('Consulta de faturas')
            ->assertDontSee('Registrar pagamento da fatura')
            ->assertDontSee('Adicionar juros/encargos manualmente');

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->assertSet('invoiceModalOpen', true)
            ->assertSee('Consulta de faturas')
            ->call('closeInvoices')
            ->assertSet('invoiceModalOpen', false);
    }
}
