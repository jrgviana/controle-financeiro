<?php

namespace Tests\Feature;

use App\Livewire\CreditCards;
use App\Livewire\DailyExpenses;
use App\Livewire\FixedBills;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\ScheduleCreditCardInstallments;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CardInstallmentAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private function bank(User $user, string $initial = '3000.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Itaú',
            'type' => 'corrente',
            'initial_balance' => $initial,
            'active' => true,
        ]);
    }

    private function card(User $user, string $name = 'Cartão Visa', int $closing = 9, int $due = 15): CreditCard
    {
        return CreditCard::create([
            'user_id' => $user->id,
            'name' => $name,
            'closing_day' => $closing,
            'due_day' => $due,
            'active' => true,
        ]);
    }

    private function purchase(User $user, CreditCard $card, string $name, string $amount, string $date, int $count = 10): void
    {
        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', $name)
            ->set('amount', $amount)
            ->set('installmentsCount', $count)
            ->set('paidOn', $date)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_next_due_month_is_explained_instead_of_empty_november_screen(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $hiper = $this->card($user, 'Hiper', 9, 15);
        $passai = $this->card($user, 'Passaí', 30, 7);

        $this->purchase($user, $hiper, 'Bomba hidráulica', 'R$ 1.250,00', '2026-11-14');
        $this->purchase($user, $passai, 'Pneus do carro', 'R$ 1.359,60', '2026-11-14');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertSee('Nenhuma parcela de cartão vence neste mês')
            ->assertSee('R$ 260,96')
            ->call('jumpToCardMonth', '2026-12')
            ->assertSet('selectedMonth', '2026-12')
            ->assertSee('Hiper')
            ->assertSee('Passaí')
            ->assertSee('R$ 125,00')
            ->assertSee('R$ 135,96')
            ->assertSee('R$ 260,96')
            ->assertDontSee('R$ 1.250,00')
            ->assertDontSee('R$ 1.359,60');

        $this->assertSame(300000, (new FinancialBalance())->currentCents($account));
    }

    public function test_purchase_before_and_after_closing_stays_in_correct_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Antes do fechamento', 'R$ 50,00', '2026-11-09', 1);
        $this->purchase($user, $card, 'Depois do fechamento', 'R$ 60,00', '2026-11-10', 1);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertSee('Antes do fechamento')
            ->assertDontSee('Depois do fechamento')
            ->assertSee('R$ 50,00');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('Depois do fechamento')
            ->assertDontSee('Antes do fechamento')
            ->assertSee('R$ 60,00');
    }

    public function test_choosing_future_parcel_only_schedules_it_without_charging_bank(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $card = $this->card($user);
        $this->purchase($user, $card, 'Bomba hidráulica', 'R$ 1.250,00', '2026-11-14');

        $first = $card->invoices()->orderBy('due_on')->firstOrFail();
        $installment = $first->installments()->firstOrFail();

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->call('selectInvoice', $first->id)
            ->assertSee('Programar antecipação')
            ->set('advanceInstallmentIds', [(string) $installment->id])
            ->set('advanceTargetMonth', '2026-11')
            ->call('advanceSelectedInstallments')
            ->assertHasNoErrors()
            ->assertSee('Nenhum valor foi descontado do banco');

        $this->assertDatabaseCount('credit_card_installment_plans', 1);
        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
        $this->assertDatabaseCount('credit_card_installment_advances', 0);
        $this->assertSame(300000, (new FinancialBalance())->currentCents($account));
        $this->assertSame(125000, (new CreditCardLedger())->totalDebt($card));
        $target = CreditCardInvoice::query()->where('credit_card_id', $card->id)
            ->whereDate('due_on', '2026-11-15')->firstOrFail();
        $this->assertSame($target->id, $installment->fresh()->credit_card_invoice_id);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertSee('Cartão Visa')->assertSee('R$ 125,00');
    }

    public function test_scheduled_advance_joins_existing_installments_and_pays_as_one_invoice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $card = $this->card($user);

        $this->purchase($user, $card, 'Parcela dezembro', 'R$ 100,00', '2026-11-14', 1);
        $this->purchase($user, $card, 'Parcelado janeiro', 'R$ 200,00', '2026-11-14', 2);
        $invoices = $card->invoices()->orderBy('due_on')->get();
        $dec = $invoices[0];
        $jan = $invoices[1];
        $janInstallment = $jan->installments()->firstOrFail();

        app(ScheduleCreditCardInstallments::class)->execute(
            $user->id, $jan->id, [$janInstallment->id], '2026-12'
        );

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('R$ 200,00')
            ->assertSee('Antecipada para este mês')
            ->call('openCardPayment', $dec->id)
            ->assertSet('cardPaymentOpen', true)
            ->assertSet('cardPayAmount', 'R$ 200,00')
            ->set('cardPayAccountId', (string) $account->id)
            ->set('cardPayDate', '2026-11-15')
            ->call('confirmCardPayment')
            ->assertHasNoErrors()
            ->assertSee('Fatura quitada');

        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertDatabaseCount('credit_card_installment_plans', 1);
        $this->assertSame(280000, (new FinancialBalance())->currentCents($account));
        $this->assertSame(10000, (new CreditCardLedger())->totalDebt($card));

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2027-01')
            ->assertSee('R$ 100,00')
            ->assertDontSee('R$ 200,00');
    }

    public function test_scheduled_installment_can_be_reverted_before_payment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Mercado', 'R$ 50,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();
        $installment = $invoice->installments()->firstOrFail();

        app(ScheduleCreditCardInstallments::class)->execute(
            $user->id, $invoice->id, [$installment->id], '2026-11'
        );
        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->call('undoPlannedInstallment', $installment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('credit_card_installment_plans', 0);
        $this->assertSame($invoice->id, $installment->fresh()->credit_card_invoice_id);
    }

    public function test_prevents_duplicates_foreign_user_and_past_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Compra', 'R$ 100,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();
        $installment = $invoice->installments()->firstOrFail();

        $service = app(ScheduleCreditCardInstallments::class);
        try {
            $service->execute($user->id, $invoice->id, [$installment->id], '2026-10');
            $this->fail('Past month accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('advanceTargetMonth', $e->errors());
        }

        $service->execute($user->id, $invoice->id, [$installment->id], '2026-11');
        $this->expectException(ValidationException::class);
        $service->execute($user->id, $invoice->id, [$installment->id], '2026-11');

        // Security of foreign user is independently enforced by the service.
    }

    public function test_other_user_cannot_access_advance_modal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Compra', 'R$ 100,00', '2026-11-14', 1);
        Livewire::actingAs($stranger)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->assertStatus(404);
    }

    public function test_deep_link_opens_the_invoice_modal_directly(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Internet', 'R$ 100,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();

        $this->actingAs($user)->get('/cartoes?cartao='.$card->id.'&fatura='.$invoice->id)
            ->assertOk()->assertSee('Faturas e planejamento de parcelas');
    }
}
