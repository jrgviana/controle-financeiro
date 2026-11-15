<?php

namespace Tests\Feature;

use App\Livewire\CreditCards;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardInvoicePayment;
use App\Models\CreditCardPurchase;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Support\CreditCardCycle;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditCardsTest extends TestCase
{
    use RefreshDatabase;

    private function card(User $user, int $closing = 10, int $due = 15, string $name = 'Itaú Mastercard'): CreditCard
    {
        return CreditCard::create([
            'user_id' => $user->id,
            'name' => $name,
            'closing_day' => $closing,
            'due_day' => $due,
            'active' => true,
        ]);
    }

    private function account(User $user, string $initial = '3000.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Conta corrente',
            'type' => 'corrente',
            'initial_balance' => $initial,
            'active' => true,
        ]);
    }

    private function registerPurchase(User $user, CreditCard $card, string $description, string $amount, int $count, string $date)
    {
        return Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->set('purchaseDescription', $description)
            ->set('purchaseAmount', $amount)
            ->set('installmentsCount', $count)
            ->set('purchaseOn', $date)
            ->call('savePurchase');
    }

    public function test_only_authenticated_user_can_open_cards(): void
    {
        $this->get('/cartoes')->assertRedirect('/login');
    }

    public function test_user_can_create_card_with_closing_and_due_day(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(CreditCards::class)
            ->set('cardName', 'Neon Visa')
            ->set('cardIssuer', 'Neon')
            ->set('cardBrand', 'Visa')
            ->set('cardLimit', 'R$ 2.500,00')
            ->set('closingDay', '10')
            ->set('dueDay', '15')
            ->call('saveCard')
            ->assertHasNoErrors()
            ->assertSee('Neon Visa');

        $this->assertDatabaseHas('credit_cards', [
            'user_id' => $user->id,
            'name' => 'Neon Visa',
            'credit_limit' => '2500.00',
            'closing_day' => 10,
            'due_day' => 15,
        ]);
    }

    public function test_close_day_is_inclusive_and_next_day_enters_following_invoice(): void
    {
        $user = User::factory()->create();
        $card = $this->card($user);

        $cycle = new CreditCardCycle();
        $this->assertSame('2026-11-15', $cycle->firstDue($card, '2026-11-10')->toDateString());
        $this->assertSame('2026-12-15', $cycle->firstDue($card, '2026-11-11')->toDateString());
    }

    public function test_due_before_closing_is_placed_in_next_month_and_short_month_is_clamped(): void
    {
        $user = User::factory()->create();
        $card = $this->card($user, 30, 5);

        $cycle = new CreditCardCycle();
        $this->assertSame('2027-03-05', $cycle->firstDue($card, '2027-02-28')->toDateString());
        $this->assertSame('2027-04-05', $cycle->firstDue($card, '2027-03-01')->toDateString());
    }

    public function test_cash_purchase_generates_one_of_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $card = $this->card($user);

        $this->registerPurchase($user, $card, 'Combustível', 'R$ 82,78', 1, '2026-11-11')
            ->assertHasNoErrors()
            ->assertSee('1 de 1');

        $this->assertDatabaseCount('credit_card_purchases', 1);
        $this->assertDatabaseCount('credit_card_installments', 1);
        $this->assertSame('2026-12-15', $card->invoices()->firstOrFail()->due_on->toDateString());
        $this->assertDatabaseHas('credit_card_installments', ['installment_number' => 1, 'amount' => '82.78']);
    }

    public function test_installments_are_generated_in_separate_invoices_without_losing_cents(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $card = $this->card($user);
        $this->registerPurchase($user, $card, 'Compra parcelada', 'R$ 100,00', 3, '2026-11-11')
            ->assertHasNoErrors();

        $purchase = CreditCardPurchase::firstOrFail();
        $installments = $purchase->installments()->orderBy('installment_number')->get();

        $this->assertSame(['33.33', '33.33', '33.34'], $installments->pluck('amount')->all());
        $this->assertSame(10000, $installments->sum(fn ($row) => FinancialBalance::cents($row->amount)));
        $this->assertSame(
            ['2026-12-15', '2027-01-15', '2027-02-15'],
            $installments->map(fn ($row) => $row->invoice->due_on->toDateString())->all()
        );
        $this->assertDatabaseCount('credit_card_invoices', 3);
    }

    public function test_purchase_does_not_debit_bank_account(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user, '1000.00');
        $card = $this->card($user);

        $this->registerPurchase($user, $card, 'Mercado', 'R$ 300,00', 3, '2026-11-11')
            ->assertHasNoErrors();

        $this->assertSame(100000, (new FinancialBalance())->currentCents($account));
        $this->assertSame(30000, (new CreditCardLedger())->totalDebt($card));
        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_partial_payment_carries_residual_to_next_invoice_and_manual_interest_is_separate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user);
        $card = $this->card($user);

        $this->registerPurchase($user, $card, 'Celular', 'R$ 1.200,00', 2, '2026-11-09')
            ->assertHasNoErrors();

        $invoices = $card->invoices()->orderBy('due_on')->get();
        $first = $invoices[0];
        $second = $invoices[1];

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->call('selectInvoice', $first->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paymentAmount', 'R$ 250,00')
            ->set('paymentOn', '2026-11-25')
            ->call('payInvoice')
            ->assertHasNoErrors()
            ->assertSet('selectedInvoiceId', (string) $second->id)
            ->assertSee('Pagamento parcial registrado')
            ->set('interestAmount', 'R$ 12,50')
            ->call('addInterest')
            ->assertHasNoErrors();

        $rows = (new CreditCardLedger())->invoices($card);
        $firstRow = $rows->first(fn ($row) => $row['invoice']->id === $first->id);
        $nextRow = $rows->first(fn ($row) => $row['invoice']->id === $second->id);

        $this->assertSame(60000, $firstRow['installments_cents']);
        $this->assertSame(25000, $firstRow['paid_cents']);
        $this->assertSame(35000, $firstRow['remaining_cents']);
        $this->assertSame(35000, $nextRow['carry_cents']);
        $this->assertSame(60000, $nextRow['installments_cents']);
        $this->assertSame(1250, $nextRow['interest_cents']);
        $this->assertSame(96250, $nextRow['remaining_cents']);

        $this->assertSame(275000, (new FinancialBalance())->currentCents($account));
        $this->assertDatabaseHas('credit_card_invoice_charges', [
            'credit_card_invoice_id' => $second->id,
            'amount' => '12.50',
        ]);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
    }

    public function test_partial_payment_creates_next_invoice_when_no_next_installment_exists(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user);
        $card = $this->card($user);

        $this->registerPurchase($user, $card, 'Mercado', 'R$ 200,00', 1, '2026-11-09')->assertHasNoErrors();

        $first = $card->invoices()->firstOrFail();

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectInvoice', $first->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paymentAmount', 'R$ 50,00')
            ->call('payInvoice')
            ->assertHasNoErrors();

        $this->assertTrue(
            $card->invoices()->get()->contains(fn ($invoice) => $invoice->due_on->toDateString() === '2026-12-15')
        );

        $rows = (new CreditCardLedger())->invoices($card);
        $this->assertSame(15000, $rows->last()['carry_cents']);
        $this->assertSame(15000, $rows->last()['remaining_cents']);
    }

    public function test_payment_above_invoice_total_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user);
        $card = $this->card($user);
        $this->registerPurchase($user, $card, 'Mercado', 'R$ 200,00', 1, '2026-11-09')->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();
        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paymentAmount', 'R$ 201,00')
            ->call('payInvoice')
            ->assertHasErrors(['paymentAmount']);

        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
    }

    public function test_payment_cannot_exceed_bank_balance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user, '80.00');
        $card = $this->card($user);
        $this->registerPurchase($user, $card, 'Mercado', 'R$ 200,00', 1, '2026-11-09')->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();
        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paymentAmount', 'R$ 100,00')
            ->call('payInvoice')
            ->assertHasErrors(['paymentAmount']);

        $this->assertSame(8000, (new FinancialBalance())->currentCents($account));
        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
    }

    public function test_manual_interest_is_not_generated_automatically(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user);
        $card = $this->card($user);
        $this->registerPurchase($user, $card, 'Mercado', 'R$ 200,00', 1, '2026-11-09')->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();
        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paymentAmount', 'R$ 50,00')
            ->call('payInvoice')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('credit_card_invoice_charges', 0);
    }

    public function test_card_data_is_scoped_to_owner(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $otherCard = $this->card($owner, name: 'Cartão privado');
        $myCard = $this->card($viewer, name: 'Cartão pessoal');

        Livewire::actingAs($viewer)->test(CreditCards::class)
            ->assertDontSee('Cartão privado')
            ->assertSee('Cartão pessoal')
            ->call('selectCard', $otherCard->id)
            ->assertStatus(404);

        $this->assertSame($myCard->user_id, $viewer->id);
    }

    public function test_transfer_is_blocked_after_card_invoice_payment_consumes_balance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $account = $this->account($user, '100.00');
        $destination = FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Carteira',
            'type' => 'carteira',
            'initial_balance' => '0.00',
            'active' => true,
        ]);
        $card = $this->card($user);
        $this->registerPurchase($user, $card, 'Curso', 'R$ 80,00', 1, '2026-11-09')->assertHasNoErrors();

        $invoice = $card->invoices()->firstOrFail();
        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectInvoice', $invoice->id)
            ->set('paymentAccountId', (string) $account->id)
            ->set('paymentAmount', 'R$ 80,00')
            ->call('payInvoice')
            ->assertHasNoErrors();

        $this->assertSame(2000, (new FinancialBalance())->currentCents($account));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\TransferFunds::class)->execute(
            $user->id, $account->id, $destination->id, 3000, '2026-11-25',
        );
    }

    public function test_paid_invoice_is_not_counted_as_daily_expense(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->assertSame(0, DailyExpense::count());
        $this->assertSame(0, CreditCardInvoicePayment::count());
    }
}
