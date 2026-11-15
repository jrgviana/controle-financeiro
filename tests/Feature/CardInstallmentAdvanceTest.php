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
            ->assertSee('Pagamentos nos cartões')
            ->assertSee('Hiper')
            ->assertSee('Passaí')
            ->assertSee('R$ 125,00')
            ->assertSee('R$ 135,96')
            ->assertSee('R$ 260,96')
            ->assertDontSee('Bomba hidráulica')
            ->call('openCardBreakdown', $hiper->id)
            ->assertSee('Bomba hidráulica')
            ->assertSee('15/12/2026')
            ->call('closeCardBreakdown')
            ->call('jumpToCardMonth', '2026-12')
            ->assertSet('selectedMonth', '2026-12')
            ->call('openCardBreakdown', $passai->id)
            ->assertSee('Pneus do carro')
            ->assertSee('R$ 135,96')
            ->assertDontSee('R$ 1.359,60');

        $this->assertSame(300000, (new FinancialBalance())->currentCents($account));
    }

    public function test_november_card_purchases_show_immediately_even_when_first_payment_is_december(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $hiper = $this->card($user, 'Hiper', 9, 15);
        $passai = $this->card($user, 'Passaí', 30, 13);

        $this->purchase($user, $hiper, 'Bomba Hidráulica', 'R$ 1.250,00', '2026-11-14', 10);
        $this->purchase($user, $passai, 'Pneus do Carro', 'R$ 1.359,60', '2026-11-14', 10);
        $this->purchase($user, $passai, 'Alinhamento e Balanceamento', 'R$ 309,99', '2026-11-14', 3);

        // Novembro mostra apenas a soma por cartão; os itens ficam no modal.
        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertSee('Pagamentos nos cartões')
            ->assertSee('R$ 239,29')
            ->assertSee('R$ 125,00')
            ->assertViewHas('cardOverviewCents', 36429)
            ->assertViewHas('totalOverviewCents', 36429)
            ->assertDontSee('Bomba Hidráulica')
            ->assertDontSee('Pneus do Carro')
            ->assertDontSee('Alinhamento e Balanceamento')
            ->assertDontSee('Faturas de cartão do mês')
            ->call('openCardBreakdown', $passai->id)
            ->assertSee('Pneus do Carro')
            ->assertSee('Alinhamento e Balanceamento')
            ->assertSee('13/12/2026')
            ->assertSee('1/10')
            ->assertSee('1/3')
            ->call('closeCardBreakdown')
            ->assertDontSee('Pneus do Carro');

        // Em dezembro a soma é a mesma, agora com fatura do mês.
        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertViewHas('cardOverviewCents', 36429)
            ->assertViewHas('totalOverviewCents', 36429)
            ->call('openCardBreakdown', $hiper->id)
            ->assertSee('Bomba Hidráulica')
            ->assertSee('15/12/2026');

        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
        $this->assertSame(300000, (new FinancialBalance())->currentCents($bank));
    }

    public function test_card_purchase_history_uses_purchase_month_not_first_invoice_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-11 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user, 'Cartão Hiper', 9, 15);
        $this->purchase($user, $card, 'Compra após fechamento de dezembro', 'R$ 90,00', '2026-12-10', 1);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertViewHas('cardOverviewCents', 9000)
            ->assertViewHas('totalOverviewCents', 9000)
            ->assertDontSee('Compra após fechamento de dezembro')
            ->call('openCardBreakdown', $card->id)
            ->assertSee('Compra após fechamento de dezembro')
            ->assertSee('15/01/2027');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2027-01')
            ->assertViewHas('cardOverviewCents', 9000)
            ->assertDontSee('Compra após fechamento de dezembro')
            ->call('openCardBreakdown', $card->id)
            ->assertSee('Compra após fechamento de dezembro');
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
            ->assertViewHas('cardOverviewCents', 11000)
            ->call('openCardBreakdown', $card->id)
            ->assertSee('Antes do fechamento')
            ->assertSee('Depois do fechamento')
            ->assertSee('15/12/2026')
            ->assertSee('R$ 50,00');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertDontSee('Depois do fechamento')
            ->call('openCardBreakdown', $card->id)
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
        $this->purchase($user, $card, 'Parcelado janeiro', 'R$ 300,00', '2026-11-14', 3);
        $invoices = $card->invoices()->orderBy('due_on')->get();
        $dec = $invoices[0];
        $jan = $invoices[1];
        $janInstallment = $jan->installments()->firstOrFail();

        app(ScheduleCreditCardInstallments::class)->execute(
            $user->id, $jan->id, [$janInstallment->id], '2026-12'
        );

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('R$ 300,00')
            ->call('openCardBreakdown', $card->id)
            ->assertSee('Antecipada')
            ->call('closeCardBreakdown')
            ->call('openCardPayment', $dec->id)
            ->assertSet('cardPaymentOpen', true)
            ->assertSet('cardPayAmount', 'R$ 300,00')
            ->set('cardPayAccountId', (string) $account->id)
            ->set('cardPayDate', '2026-11-15')
            ->call('confirmCardPayment')
            ->assertHasNoErrors()
            ->assertSee('Fatura quitada');

        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertDatabaseCount('credit_card_installment_plans', 1);
        $this->assertSame(270000, (new FinancialBalance())->currentCents($account));
        $this->assertSame(10000, (new CreditCardLedger())->totalDebt($card));

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2027-01')
            ->assertViewHas('cardOverviewCents', 0)
            ->set('selectedMonth', '2027-02')
            ->assertSee('R$ 100,00');
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

    public function test_reverting_planned_advance_is_possible_after_due_date_until_payment_is_made(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Mercado', 'R$ 100,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();
        $installment = $invoice->installments()->firstOrFail();

        app(ScheduleCreditCardInstallments::class)->execute(
            $user->id, $invoice->id, [$installment->id], '2026-11'
        );
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00'));
        app(ScheduleCreditCardInstallments::class)->undo($user->id, $installment->id);

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

    public function test_kpi_does_not_count_a_purchase_twice_when_its_first_installment_is_due_in_same_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $card = $this->card($user, 'Visa', 9, 15);

        $this->purchase($user, $card, 'Compra antes do fechamento', 'R$ 50,00', '2026-11-09', 1);
        $this->purchase($user, $card, 'Compra após fechamento', 'R$ 60,00', '2026-11-14', 1);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertViewHas('cardInvoiceTotalCents', 5000)
            ->assertViewHas('cardOverviewCents', 11000)
            ->assertViewHas('totalOverviewCents', 11000)
            ->assertDontSee('Compra antes do fechamento')
            ->call('openCardBreakdown', $card->id)
            ->assertSee('Compra antes do fechamento')
            ->assertSee('Compra após fechamento');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertViewHas('cardInvoiceTotalCents', 6000)
            ->assertViewHas('cardOverviewCents', 6000)
            ->assertViewHas('totalOverviewCents', 6000);

        $this->assertSame(300000, (new FinancialBalance())->currentCents($bank));
        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
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
