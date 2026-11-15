<?php

namespace Tests\Feature;

use App\Livewire\DailyExpenses;
use App\Livewire\Payments;
use App\Models\AccountTransfer;
use App\Models\CreditCard;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ReceivedIncome;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use App\Models\User;
use App\Services\PayRecurringMonthlyBill;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use App\Support\PaymentAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $balance = '0.00', string $name = 'Itaú'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'corrente',
            'initial_balance' => $balance,
            'active' => true,
        ]);
    }

    private function recurring(User $user, string $name = 'Energia', string $amount = '200.00', int $dueDay = 10): RecurringBill
    {
        return RecurringBill::create([
            'user_id' => $user->id,
            'description' => $name,
            'category' => 'moradia',
            'due_day' => $dueDay,
            'planned_amount' => $amount,
            'starts_on' => '2026-11-01',
            'active' => true,
        ]);
    }

    private function card(User $user, string $name = 'Passaí'): CreditCard
    {
        return CreditCard::create([
            'user_id' => $user->id,
            'name' => $name,
            'credit_limit' => '5000.00',
            'closing_day' => 9,
            'due_day' => 15,
            'active' => true,
        ]);
    }

    private function cardPurchase(User $user, CreditCard $card, string $amount = 'R$ 200,00', int $count = 1): void
    {
        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('paidOn', '2026-11-14')
            ->set('description', 'Mercado no cartão')
            ->set('amount', $amount)
            ->set('installmentsCount', $count)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_login_is_required_to_open_payments(): void
    {
        $this->get('/pagamentos')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->get('/pagamentos')->assertOk()->assertSee('Pagamentos');
    }

    public function test_real_example_thousand_received_minus_120_spent_leaves_880_to_pay(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->account($user);
        ReceivedIncome::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'received_on' => '2026-11-15',
            'description' => 'Salário',
            'category' => 'salario',
            'amount' => '1000.00',
        ]);
        DailyExpense::create([
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'paid_on' => '2026-11-15',
            'description' => 'Mercado',
            'category' => 'alimentacao',
            'amount' => '120.00',
        ]);

        $plan = $this->recurring($user);

        Livewire::actingAs($user)->test(Payments::class)
            ->assertSee('Recebimentos do mês')
            ->assertSee('R$ 1.000,00')
            ->assertSee('R$ 120,00')
            ->assertSee('Disponível para pagar agora')
            ->assertSee('R$ 880,00')
            ->assertSee('Energia')
            ->assertSee('Vencida')
            ->call('openPayment', 'recurring:'.$plan->id.':2026-11')
            ->assertSet('paymentAmount', 'R$ 200,00')
            ->set('paymentAccountId', (string) $account->id)
            ->set('paidOn', '2026-11-15')
            ->call('confirmPayment')
            ->assertHasNoErrors()
            ->assertSee('R$ 680,00');

        $this->assertSame(68000, app(FinancialBalance::class)->currentCents($account));
        $this->assertDatabaseCount('daily_expenses', 2);
        $this->assertDatabaseCount('scheduled_bills', 1);
        $this->assertNotNull(ScheduledBill::firstOrFail()->daily_expense_id);
        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
    }

    public function test_unpaid_november_account_remains_overdue_in_december_with_original_due_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00'));
        $user = User::factory()->create();
        $account = $this->account($user, '1000.00');
        $plan = $this->recurring($user, 'Água', '80.00', 10);

        $view = Livewire::actingAs($user)->test(Payments::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('Água')
            ->assertSee('10/11/2026')
            ->assertSee('10/12/2026')
            ->assertSee('Vencida')
            ->assertSee('A vencer');

        $this->assertDatabaseCount('scheduled_bills', 0);

        $view->call('openPayment', 'recurring:'.$plan->id.':2026-11')
            ->set('paymentAccountId', (string) $account->id)
            ->set('paidOn', '2026-12-03')
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('scheduled_bills', 1);
        $this->assertDatabaseHas('scheduled_bills', ['recurring_bill_id' => $plan->id, 'amount' => '80.00']);
        $rows = app(PaymentAgenda::class)->forMonth($user->id, '2026-12')['pending'];
        $this->assertCount(1, $rows);
        $this->assertSame('2026-12-10', $rows->first()['due_on']->toDateString());
        $this->assertSame(92000, app(FinancialBalance::class)->currentCents($account));
    }

    public function test_unpaid_old_single_bill_is_not_duplicated_in_following_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-03 12:00'));
        $user = User::factory()->create();
        $account = $this->account($user, '150.00');
        $bill = ScheduledBill::create([
            'user_id' => $user->id,
            'description' => 'Condomínio',
            'category' => 'moradia',
            'due_on' => '2026-11-15',
            'amount' => '125.00',
        ]);

        foreach (['2026-11', '2026-12', '2027-01'] as $period) {
            $rows = app(PaymentAgenda::class)->forMonth($user->id, $period)['pending'];
            $this->assertCount(1, $rows);
            $this->assertSame($bill->id, $rows->first()['id']);
            $this->assertSame('Vencida', $rows->first()['status']);
            $this->assertSame(12500, $rows->first()['amount_cents']);
        }

        Livewire::actingAs($user)->test(Payments::class)
            ->call('openPayment', 'bill:'.$bill->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paidOn', '2027-01-03')
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('scheduled_bills', 1);
        $this->assertDatabaseCount('daily_expenses', 1);
        $this->assertCount(0, app(PaymentAgenda::class)->forMonth($user->id, '2027-01')['pending']);
    }

    public function test_insufficient_balance_does_not_create_monthly_occurrence_or_payment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->account($user, '90.00');
        $plan = $this->recurring($user, 'Energia', '120.00');

        Livewire::actingAs($user)->test(Payments::class)
            ->call('openPayment', 'recurring:'.$plan->id.':2026-11')
            ->set('paymentAccountId', (string) $account->id)
            ->call('confirmPayment')
            ->assertHasErrors(['paymentAmount'])
            ->assertSee('Saldo insuficiente');

        $this->assertDatabaseCount('scheduled_bills', 0);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertSame(9000, app(FinancialBalance::class)->currentCents($account));
    }

    public function test_monthly_bill_amount_can_be_changed_without_affecting_future_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, '1000.00');
        $plan = $this->recurring($user, 'Luz', '150.00');

        Livewire::actingAs($user)->test(Payments::class)
            ->set('selectedMonth', '2026-11')
            ->call('openPayment', 'recurring:'.$plan->id.':2026-11')
            ->set('paymentAccountId', (string) $bank->id)
            ->set('billTotalAmount', 'R$ 177,45')
            ->set('paymentAmount', 'R$ 177,45')
            ->set('paidOn', '2026-12-01')
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $this->assertSame('150.00', $plan->fresh()->planned_amount);
        $this->assertDatabaseHas('scheduled_bills', ['recurring_bill_id' => $plan->id, 'amount' => '177.45']);
        $rows = app(PaymentAgenda::class)->forMonth($user->id, '2026-12')['pending'];
        $this->assertCount(1, $rows);
        $this->assertSame(15000, $rows->first()['amount_cents']);
        $this->assertSame(82255, app(FinancialBalance::class)->currentCents($bank));
    }

    public function test_card_invoice_can_be_partially_paid_and_unpaid_debt_rolls_forward_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, '500.00');
        $card = $this->card($user);
        $this->cardPurchase($user, $card, 'R$ 200,00', 2);

        $nov = app(PaymentAgenda::class)->forMonth($user->id, '2026-11');
        $this->assertCount(0, $nov['pending']);
        $this->assertSame('2026-12-15', $nov['next_card_due']->toDateString());

        $dec = app(PaymentAgenda::class)->forMonth($user->id, '2026-12');
        $this->assertCount(1, $dec['pending']);
        $this->assertSame(10000, $dec['pending']->first()['amount_cents']);
        $invoiceId = $dec['pending']->first()['id'];

        Livewire::actingAs($user)->test(Payments::class)
            ->set('selectedMonth', '2026-12')
            ->call('openPayment', 'card:'.$invoiceId)
            ->set('paymentAccountId', (string) $bank->id)
            ->set('paymentAmount', 'R$ 40,00')
            ->set('paidOn', '2026-11-15')
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $this->assertSame(46000, app(FinancialBalance::class)->currentCents($bank));
        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertSame(16000, app(CreditCardLedger::class)->totalDebt($card));

        $this->travelTo(CarbonImmutable::parse('2027-01-05 12:00'));
        $jan = app(PaymentAgenda::class)->forMonth($user->id, '2027-01');
        $this->assertSame(16000, $jan['pending_cents']);
        $this->assertCount(2, $jan['pending']);
        $this->assertSame(6000, $jan['pending']->first()['amount_cents']);
        $this->assertSame('Vencida', $jan['pending']->first()['status']);
    }

    public function test_payments_respect_account_specific_balance_and_do_not_count_transfers_as_income(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $first = $this->account($user, '300.00', 'Itaú');
        $second = $this->account($user, '0.00', 'Neon');
        $plan = $this->recurring($user, 'Condomínio', '250.00');

        AccountTransfer::create([
            'user_id' => $user->id,
            'from_account_id' => $first->id,
            'to_account_id' => $second->id,
            'transferred_on' => '2026-11-15',
            'amount' => '200.00',
        ]);

        $agenda = app(PaymentAgenda::class)->forMonth($user->id, '2026-11');
        $this->assertSame(30000, $agenda['available_cents']);
        $this->assertSame(0, $agenda['income_cents']);
        $this->assertSame(10000, $agenda['accounts']->firstWhere('id', $first->id)['usable_cents']);
        $this->assertSame(20000, $agenda['accounts']->firstWhere('id', $second->id)['usable_cents']);

        Livewire::actingAs($user)->test(Payments::class)
            ->call('openPayment', 'recurring:'.$plan->id.':2026-11')
            ->set('paymentAccountId', (string) $second->id)
            ->call('confirmPayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertDatabaseCount('scheduled_bills', 0);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_other_user_cannot_pay_someone_elses_account_or_bill(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $victimBank = $this->account($owner, '1000.00');
        $intruderBank = $this->account($intruder, '1000.00');
        $victimBill = $this->recurring($owner);

        Livewire::actingAs($intruder)->test(Payments::class)
            ->call('openPayment', 'recurring:'.$victimBill->id.':2026-11')
            ->assertStatus(404);

        $own = $this->recurring($intruder, 'Internet', '100.00');
        Livewire::actingAs($intruder)->test(Payments::class)
            ->call('openPayment', 'recurring:'.$own->id.':2026-11')
            ->set('paymentAccountId', (string) $victimBank->id)
            ->call('confirmPayment')
            ->assertHasErrors(['paymentAccountId']);

        $this->assertSame(100000, app(FinancialBalance::class)->currentCents($victimBank));
        $this->assertSame(100000, app(FinancialBalance::class)->currentCents($intruderBank));
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_marking_month_as_unpaid_moves_the_same_debt_to_next_month_without_deducting_money(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, '500.00');
        $bill = $this->recurring($user, 'Energia', '316.75', 17);
        $key = 'recurring:'.$bill->id.':2026-11';

        Livewire::actingAs($user)->test(Payments::class)
            ->assertSee('Energia')
            ->call('openPostpone', $key)
            ->assertSee('Não pagar neste mês?')
            ->set('postponeReason', 'Faltou dinheiro')
            ->call('confirmPostpone')
            ->assertHasNoErrors()
            ->assertSee('Não pago')
            ->assertSee('Faltou dinheiro')
            ->assertDontSee('Vence 17/11/2026')
            ->assertSee('R$ 316,75');

        $this->assertDatabaseCount('payment_postponements', 1);
        $this->assertDatabaseCount('scheduled_bills', 0);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertSame(50000, app(FinancialBalance::class)->currentCents($bank));

        $dec = app(PaymentAgenda::class)->forMonth($user->id, '2026-12');
        $this->assertCount(2, $dec['pending']);
        $this->assertSame(63350, $dec['pending_cents']);
        $this->assertSame('2026-11-17', $dec['pending']->first()['due_on']->toDateString());

        // Cada mês pode registrar outra decisão, mantendo o histórico.
        $current = Livewire::actingAs($user)->test(Payments::class)
            ->set('selectedMonth', '2026-12')
            ->call('openPostpone', $key)
            ->call('confirmPostpone')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('payment_postponements', 2);
        $this->assertCount(2, app(PaymentAgenda::class)->forMonth($user->id, '2026-12')['pending']);

        $jan = app(PaymentAgenda::class)->forMonth($user->id, '2027-01');
        $this->assertCount(3, $jan['pending']);
        $this->assertSame(95025, $jan['pending_cents']);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_manual_postponement_can_be_undone_without_creating_a_payment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bill = ScheduledBill::create([
            'user_id' => $user->id, 'description' => 'Internet',
            'category' => 'moradia', 'due_on' => '2026-11-12', 'amount' => '90.00',
        ]);

        Livewire::actingAs($user)->test(Payments::class)
            ->call('openPostpone', 'bill:'.$bill->id)
            ->call('confirmPostpone')
            ->assertHasNoErrors()
            ->assertSee('Desfazer')
            ->call('undoPostpone', \App\Models\PaymentPostponement::firstOrFail()->id)
            ->assertHasNoErrors()
            ->assertSee('Internet');

        $this->assertDatabaseCount('payment_postponements', 0);
        $this->assertCount(1, app(PaymentAgenda::class)->forMonth($user->id, '2026-11')['pending']);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_partial_monthly_payment_only_carries_outstanding_and_preserves_original_bill_value(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, '1000.00');
        $plan = $this->recurring($user, 'Energia', '300.00', 10);
        $key = 'recurring:'.$plan->id.':2026-11';

        Livewire::actingAs($user)->test(Payments::class)
            ->call('openPayment', $key)
            ->set('billTotalAmount', 'R$ 316,75')
            ->set('paymentAmount', 'R$ 100,00')
            ->set('paymentAccountId', (string) $bank->id)
            ->call('confirmPayment')
            ->assertHasNoErrors()
            ->assertSee('R$ 216,75');

        $bill = ScheduledBill::firstOrFail();
        $this->assertSame('316.75', $bill->amount);
        $this->assertNull($bill->daily_expense_id);
        $this->assertSame(21675, $bill->remainingCents());
        $this->assertSame(90000, app(FinancialBalance::class)->currentCents($bank));
        $this->assertDatabaseCount('scheduled_bill_payments', 1);
        $this->assertDatabaseCount('payment_postponements', 1);

        $nov = app(PaymentAgenda::class)->forMonth($user->id, '2026-11');
        $this->assertCount(0, $nov['pending']);
        $this->assertSame('partial', $nov['postponed_this_month']->first()->source);
        $dec = app(PaymentAgenda::class)->forMonth($user->id, '2026-12');
        $this->assertSame(21675, $dec['pending']->first()['amount_cents']);
        $this->assertSame(10000, $dec['pending']->first()['paid_cents']);
        $this->assertSame(30000, $dec['pending']->last()['amount_cents']);

        Livewire::actingAs($user)->test(Payments::class)
            ->set('selectedMonth', '2026-12')
            ->call('openPayment', $key)
            ->set('billTotalAmount', 'R$ 316,75')
            ->set('paymentAmount', 'R$ 216,75')
            ->set('paymentAccountId', (string) $bank->id)
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $this->assertNotNull($bill->fresh()->daily_expense_id);
        $this->assertDatabaseCount('scheduled_bill_payments', 2);
        $this->assertSame(68325, app(FinancialBalance::class)->currentCents($bank));
        $this->assertDatabaseCount('daily_expenses', 2);
        $this->assertSame('300.00', $plan->fresh()->planned_amount);
    }

    public function test_partial_one_off_payment_and_prevents_modifying_linked_daily_expense(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, '500.00');
        $bill = ScheduledBill::create([
            'user_id' => $user->id, 'description' => 'Condomínio',
            'category' => 'moradia', 'due_on' => '2026-11-10', 'amount' => '250.00',
        ]);

        Livewire::actingAs($user)->test(Payments::class)
            ->call('openPayment', 'bill:'.$bill->id)
            ->set('paymentAmount', 'R$ 80,00')
            ->set('paymentAccountId', (string) $bank->id)
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $expense = DailyExpense::firstOrFail();
        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->call('delete', $expense->id)
            ->assertSee('vinculado');
        $this->assertDatabaseHas('daily_expenses', ['id' => $expense->id, 'deleted_at' => null]);

        $this->assertSame(17000, app(PaymentAgenda::class)
            ->forMonth($user->id, '2026-12')['pending']->first()['amount_cents']);
        $this->assertSame(42000, app(FinancialBalance::class)->currentCents($bank));
        $this->assertDatabaseCount('payment_postponements', 1);
    }

    public function test_partial_card_payment_is_carried_to_next_month_without_double_counting(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, '500.00');
        $card = $this->card($user);
        $this->cardPurchase($user, $card, 'R$ 300,00');
        $invoice = $card->invoices()->firstOrFail();

        Livewire::actingAs($user)->test(Payments::class)
            ->set('selectedMonth', '2026-12')
            ->call('openPayment', 'card:'.$invoice->id)
            ->set('paymentAccountId', (string) $bank->id)
            ->set('paymentAmount', 'R$ 100,00')
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $dec = app(PaymentAgenda::class)->forMonth($user->id, '2026-12');
        $this->assertCount(0, $dec['pending']);
        $this->assertSame('partial', $dec['postponed_this_month']->first()->source);
        $jan = app(PaymentAgenda::class)->forMonth($user->id, '2027-01');
        $this->assertCount(1, $jan['pending']);
        $this->assertSame(20000, $jan['pending_cents']);
        $this->assertSame(20000, app(CreditCardLedger::class)->totalDebt($card));
        $this->assertSame(40000, app(FinancialBalance::class)->currentCents($bank));
        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_user_cannot_defer_another_users_bill(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bill = ScheduledBill::create([
            'user_id' => $owner->id, 'description' => 'Energia',
            'category' => 'moradia', 'due_on' => '2026-11-10', 'amount' => '120.00',
        ]);

        Livewire::actingAs($other)->test(Payments::class)
            ->call('openPostpone', 'bill:'.$bill->id)
            ->assertStatus(404);

        $this->assertDatabaseCount('payment_postponements', 0);
    }

    public function test_payment_cannot_be_repeated_after_first_confirmation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->account($user, '1000.00');
        $plan = $this->recurring($user);

        app(PayRecurringMonthlyBill::class)->execute(
            $user->id, $plan->id, '2026-11', 20000, $account->id, '2026-11-15'
        );

        $this->expectException(ValidationException::class);
        app(PayRecurringMonthlyBill::class)->execute(
            $user->id, $plan->id, '2026-11', 20000, $account->id, '2026-11-15'
        );
    }
}
