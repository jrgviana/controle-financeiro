<?php

namespace Tests\Feature;

use App\Livewire\FixedBills;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use App\Models\User;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FixedBillsTest extends TestCase
{
    use RefreshDatabase;

    private function bank(User $user, string $balance = '1000.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Conta Itaú',
            'type' => 'corrente',
            'initial_balance' => $balance,
            'active' => true,
        ]);
    }

    private function createFixed(User $user, string $description = 'Internet', string $amount = 'R$ 120,00', int $dueDay = 10): RecurringBill
    {
        Livewire::actingAs($user)->test(FixedBills::class)
            ->call('openFixedForm')
            ->set('description', $description)
            ->set('category', 'moradia')
            ->set('plannedAmount', $amount)
            ->set('dueDay', (string) $dueDay)
            ->set('startsMonth', '2026-11')
            ->call('saveFixed')
            ->assertHasNoErrors();

        return RecurringBill::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function prepareMonth(User $user, RecurringBill $fixed, string $month, string $amount, string $dueOn): ScheduledBill
    {
        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', $month)
            ->call('openOccurrence', $fixed->id)
            ->set('occurrenceAmount', $amount)
            ->set('occurrenceDueOn', $dueOn)
            ->call('saveOccurrence')
            ->assertHasNoErrors();

        return ScheduledBill::query()
            ->where('recurring_bill_id', $fixed->id)
            ->whereDate('period_month', $month.'-01')->firstOrFail();
    }

    public function test_fixed_bills_are_authenticated_and_accounts_page_remains_balance_only(): void
    {
        $this->get('/contas-fixas')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/contas-fixas')
            ->assertOk()->assertSee('Contas fixas')->assertSee('Nova conta fixa');

        $this->actingAs($user)->get('/contas')
            ->assertOk()->assertSee('Saldo disponível calculado')
            ->assertDontSee('Compromissos e contas a vencer')
            ->assertDontSee('Faturas de cartão a vencer');
    }

    public function test_monthly_fixed_bill_appears_in_every_month_without_debiting_account_or_materializing_future_debts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $this->createFixed($user, 'Condomínio', 'R$ 250,00', 15);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertSee('Condomínio')
            ->assertSee('15/11/2026')
            ->assertSee('R$ 250,00');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2027-01')
            ->assertSee('Condomínio')
            ->assertSee('15/01/2027')
            ->assertSee('R$ 250,00');

        $this->assertDatabaseCount('scheduled_bills', 0);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertSame(100000, (new FinancialBalance())->currentCents($bank));
    }

    public function test_summary_separates_monthly_accounts_card_invoices_and_their_combined_total(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $this->createFixed($user, 'Internet residencial', 'R$ 120,00', 10);

        $card = \App\Models\CreditCard::create([
            'user_id' => $user->id,
            'name' => 'Cartão Hiper',
            'closing_day' => 9,
            'due_day' => 15,
            'active' => true,
        ]);

        Livewire::actingAs($user)->test(\App\Livewire\DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', 'Bomba hidráulica')
            ->set('amount', 'R$ 125,00')
            ->set('installmentsCount', 1)
            ->set('paidOn', '2026-11-14')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('Contas fixas e avulsas do mês')
            ->assertSee('Faturas com vencimento no mês')
            ->assertSee('Total de compromissos do mês')
            ->assertSee('R$ 120,00')
            ->assertSee('R$ 125,00')
            ->assertSee('R$ 245,00')
            ->assertSee('Bomba hidráulica')
            ->assertSee('Parcela 1/1')
            ->assertDontSee('Ver parcelas que compõem esta fatura')
            ->assertDontSee('<details class="mt-3 rounded-lg bg-slate-50', false);

        // Um pagamento parcial não reduz a previsão original do mês:
        // apenas muda o que já foi pago e o que ainda resta pagar.
        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->call('openCardPayment', $invoice->id)
            ->set('cardPayAccountId', (string) $bank->id)
            ->set('cardPayAmount', 'R$ 50,00')
            ->set('cardPayDate', '2026-11-15')
            ->call('confirmCardPayment')
            ->assertHasNoErrors()
            ->assertSee('R$ 245,00')
            ->assertSee('R$ 50,00')
            ->assertSee('R$ 75,00')
            ->assertSee('R$ 195,00');

        $this->assertSame(95000, (new FinancialBalance())->currentCents($bank));
    }

    public function test_short_months_adjust_due_day_without_changing_saved_default(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $fixed = $this->createFixed($user, 'Condomínio', 'R$ 300,00', 31);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2027-02')
            ->assertSee('28/02/2027')
            ->call('openOccurrence', $fixed->id)
            ->assertSet('occurrenceDueOn', '2027-02-28');

        $this->assertSame(31, $fixed->fresh()->due_day);
    }

    public function test_variable_month_amount_can_be_confirmed_then_paid_once_without_extra_bank_entries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $fixed = $this->createFixed($user, 'Energia', 'R$ 200,00', 12);

        $bill = $this->prepareMonth($user, $fixed, '2026-11', 'R$ 187,42', '2026-11-12');

        $this->assertSame('187.42', $bill->amount);
        $this->assertSame(100000, (new FinancialBalance())->currentCents($bank));
        $this->assertDatabaseCount('daily_expenses', 0);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->call('openPayment', $bill->id)
            ->set('billPaymentAccountId', (string) $bank->id)
            ->set('billPaidOn', '2026-11-15')
            ->call('confirmPayment')
            ->assertHasNoErrors()
            ->assertSee('Pagamento registrado');

        $bill->refresh();
        $this->assertNotNull($bill->daily_expense_id);
        $this->assertDatabaseCount('daily_expenses', 1);
        $this->assertSame(81258, (new FinancialBalance())->currentCents($bank));
        $this->assertSame('200.00', $fixed->fresh()->planned_amount);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\PayScheduledBill::class)
            ->execute($user->id, $bill->id, $bank->id, '2026-11-15');
    }

    public function test_an_existing_month_occurrence_is_updated_not_duplicated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $fixed = $this->createFixed($user, 'Internet');
        $first = $this->prepareMonth($user, $fixed, '2026-11', 'R$ 120,00', '2026-11-10');
        $second = $this->prepareMonth($user, $fixed, '2026-11', 'R$ 135,00', '2026-11-11');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('scheduled_bills', 1);
        $this->assertSame('135.00', $second->amount);
    }

    public function test_paid_historical_month_is_preserved_when_template_is_changed_or_paused(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $fixed = $this->createFixed($user, 'Internet', 'R$ 120,00', 10);
        $bill = $this->prepareMonth($user, $fixed, '2026-11', 'R$ 120,00', '2026-11-10');
        app(\App\Services\PayScheduledBill::class)
            ->execute($user->id, $bill->id, $bank->id, '2026-11-15');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->call('openFixedForm', $fixed->id)
            ->set('plannedAmount', 'R$ 150,00')
            ->call('saveFixed')
            ->assertHasNoErrors()
            ->call('toggleFixed', $fixed->id)
            ->assertHasNoErrors()
            ->assertSee('Internet')
            ->assertSee('R$ 120,00');

        $this->assertSame('120.00', $bill->fresh()->amount);
        $this->assertSame('150.00', $fixed->fresh()->planned_amount);
        $this->assertFalse($fixed->fresh()->active);
        $this->assertDatabaseCount('daily_expenses', 1);
    }

    public function test_existing_one_off_records_remain_visible_in_fixed_bills_section(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        ScheduledBill::create([
            'user_id' => $user->id,
            'description' => 'Conta de água antiga',
            'category' => 'moradia',
            'amount' => '55.50',
            'due_on' => '2026-11-20',
        ]);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->assertSee('Contas avulsas do mês')
            ->assertSee('Conta de água antiga')
            ->assertSee('R$ 55,50');
    }

    public function test_user_cannot_change_other_users_fixed_bills(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $fixed = $this->createFixed($owner, 'Conta exclusiva');

        Livewire::actingAs($viewer)->test(FixedBills::class)
            ->assertDontSee('Conta exclusiva')
            ->call('openOccurrence', $fixed->id)
            ->assertStatus(404);
    }

    public function test_payment_more_than_available_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user, '30.00');
        $fixed = $this->createFixed($user, 'Internet', 'R$ 120,00', 10);
        $bill = $this->prepareMonth($user, $fixed, '2026-11', 'R$ 120,00', '2026-11-10');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->call('openPayment', $bill->id)
            ->set('billPaymentAccountId', (string) $bank->id)
            ->set('billPaidOn', '2026-11-15')
            ->call('confirmPayment')
            ->assertHasErrors(['billPaymentAccountId']);

        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertNull($bill->fresh()->daily_expense_id);
    }
}
