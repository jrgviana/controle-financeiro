<?php

namespace Tests\Feature;

use App\Livewire\CreditCards;
use App\Livewire\DailyExpenses;
use App\Livewire\FixedBills;
use App\Models\CreditCard;
use App\Models\CreditCardInstallment;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\AdvanceCreditCardInstallments;
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

    private function purchase(User $user, CreditCard $card, string $name, string $amount, string $purchasedOn, int $count = 10): void
    {
        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', $name)
            ->set('amount', $amount)
            ->set('installmentsCount', $count)
            ->set('paidOn', $purchasedOn)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_fixed_bills_shows_only_installments_due_in_selected_month_after_closing(): void
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
            ->assertSet('selectedMonth', '2026-11')
            ->assertDontSee('Bomba hidráulica')
            ->assertDontSee('Pneus do carro')
            ->assertSee('R$ 0,00');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('Bomba hidráulica')
            ->assertSee('Pneus do carro')
            ->assertSee('Parcela 1 de 10')
            ->assertSee('15/12/2026')
            ->assertSee('07/12/2026')
            ->assertSee('R$ 125,00')
            ->assertSee('R$ 135,96')
            ->assertSee('R$ 260,96')
            ->assertDontSee('R$ 1.250,00')
            ->assertDontSee('R$ 1.359,60');

        $this->assertSame(300000, (new FinancialBalance())->currentCents($account));
    }

    public function test_purchase_on_or_before_closing_appears_in_earlier_due_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Compra no fechamento', 'R$ 50,00', '2026-11-09', 1);
        $this->purchase($user, $card, 'Compra após fechamento', 'R$ 60,00', '2026-11-10', 1);

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-11')
            ->assertSee('Compra no fechamento')
            ->assertDontSee('Compra após fechamento')
            ->assertSee('R$ 50,00');

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('Compra após fechamento')
            ->assertDontSee('Compra no fechamento')
            ->assertSee('R$ 60,00');
    }

    public function test_modal_selection_can_advance_a_future_installment_and_debit_bank_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $card = $this->card($user);
        $this->purchase($user, $card, 'Bomba hidráulica', 'R$ 1.250,00', '2026-11-14');

        $invoice = $card->invoices()->orderBy('due_on')->firstOrFail();
        $installment = $invoice->installments()->firstOrFail();
        $this->assertSame('2026-12-15', $invoice->due_on->toDateString());

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Antecipar parcelas selecionadas')
            ->set('advanceInstallmentIds', [(string) $installment->id])
            ->assertSee('R$ 125,00')
            ->set('advanceAccountId', (string) $account->id)
            ->set('advanceOn', '2026-11-15')
            ->call('advanceSelectedInstallments')
            ->assertHasNoErrors()
            ->assertSee('Antecipada e registrada no saldo');

        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertDatabaseCount('credit_card_installment_advances', 1);
        $this->assertDatabaseHas('credit_card_installment_advances', ['credit_card_installment_id' => $installment->id]);
        $this->assertSame(287500, (new FinancialBalance())->currentCents($account));
        $this->assertSame(112500, (new CreditCardLedger())->totalDebt($card));

        Livewire::actingAs($user)->test(FixedBills::class)
            ->set('selectedMonth', '2026-12')
            ->assertSee('Bomba hidráulica')
            ->assertSee('Antecipada');

        $this->expectException(ValidationException::class);
        app(AdvanceCreditCardInstallments::class)->execute(
            $user->id, $invoice->id, [$installment->id], $account->id, '2026-11-15'
        );
    }

    public function test_advance_of_one_future_installment_does_not_carry_other_unexpired_installments_as_overdue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $account = $this->bank($user);
        $card = $this->card($user);
        $this->purchase($user, $card, 'Primeira compra', 'R$ 200,00', '2026-11-14', 2);
        $this->purchase($user, $card, 'Segunda compra', 'R$ 120,00', '2026-11-14', 2);

        $invoices = $card->invoices()->orderBy('due_on')->get();
        $this->assertCount(2, $invoices);
        $firstInstallment = $invoices[0]->installments()->orderBy('id')->firstOrFail();

        app(AdvanceCreditCardInstallments::class)->execute(
            $user->id, $invoices[0]->id, [$firstInstallment->id], $account->id, '2026-11-15'
        );

        $rows = (new CreditCardLedger())->invoices($card);
        $this->assertSame(6000, $rows[0]['remaining_cents']);
        $this->assertSame(0, $rows[1]['carry_cents']);
        $this->assertSame(16000, $rows[1]['remaining_cents']);
        $this->assertSame(22000, (new CreditCardLedger())->totalDebt($card));
    }

    public function test_advance_multiple_installments_in_one_invoice_creates_single_bank_payment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $card = $this->card($user);

        $this->purchase($user, $card, 'Mercado', 'R$ 100,00', '2026-11-14', 1);
        $this->purchase($user, $card, 'Farmácia', 'R$ 50,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();
        $ids = $invoice->installments()->orderBy('id')->pluck('id')->all();

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->call('selectInvoice', $invoice->id)
            ->set('advanceInstallmentIds', array_map('strval', $ids))
            ->set('advanceAccountId', (string) $bank->id)
            ->call('advanceSelectedInstallments')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('credit_card_installment_advances', 2);
        $this->assertDatabaseCount('credit_card_invoice_payments', 1);
        $this->assertSame(285000, (new FinancialBalance())->currentCents($bank));
        $this->assertSame(0, (new CreditCardLedger())->totalDebt($card));
    }

    public function test_advance_rejects_insufficient_bank_balance_and_unrelated_users(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $bank = $this->bank($user, '20.00');
        $card = $this->card($user);
        $this->purchase($user, $card, 'Mercado', 'R$ 100,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();
        $id = $invoice->installments()->firstOrFail()->id;

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->set('advanceInstallmentIds', [(string) $id])
            ->set('advanceAccountId', (string) $bank->id)
            ->call('advanceSelectedInstallments')
            ->assertHasErrors(['advanceAccountId']);

        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
        $this->assertSame(2000, (new FinancialBalance())->currentCents($bank));

        Livewire::actingAs($other)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->assertStatus(404);
    }

    public function test_deep_link_from_fixed_bills_opens_only_owned_invoice_in_modal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $this->purchase($user, $card, 'Internet', 'R$ 100,00', '2026-11-14', 1);
        $invoice = $card->invoices()->firstOrFail();

        $this->actingAs($user)->get('/contas-fixas')->assertOk();

        $this->actingAs($user)->get('/cartoes?cartao='.$card->id.'&fatura='.$invoice->id)
            ->assertOk()
            ->assertSee('Faturas e antecipação'); // O modal já abre na fatura vinculada.

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->call('selectInvoice', $invoice->id)
            ->assertSee('Parcela 1 de 1');
    }
}
