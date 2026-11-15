<?php

namespace Tests\Feature;

use App\Livewire\CreditCards;
use App\Livewire\DailyExpenses;
use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\PayCreditCardInvoice;
use App\Services\RecordCreditCardPurchase;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CreditCardLimitTest extends TestCase
{
    use RefreshDatabase;

    private function card(User $user, ?string $limit = '1000.00', string $name = 'Itaú Platinum'): CreditCard
    {
        return CreditCard::create([
            'user_id' => $user->id,
            'name' => $name,
            'credit_limit' => $limit,
            'closing_day' => 2,
            'due_day' => 9,
            'active' => true,
        ]);
    }

    private function purchase(User $user, CreditCard $card, string $amount, int $installments = 1)
    {
        return Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', 'Compra de teste')
            ->set('amount', $amount)
            ->set('paidOn', '2026-11-14')
            ->set('installmentsCount', $installments)
            ->call('save');
    }

    public function test_purchase_above_card_limit_is_rejected_and_not_partially_saved(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);

        $this->purchase($user, $card, 'R$ 1.200,00')
            ->assertHasErrors(['amount'])
            ->assertSee('Compra acima do limite disponível')
            ->assertSee('R$ 1.000,00');

        $this->assertDatabaseCount('credit_card_purchases', 0);
        $this->assertDatabaseCount('credit_card_invoices', 0);
        $this->assertDatabaseCount('credit_card_installments', 0);
        $this->assertSame(0, (new CreditCardLedger())->totalDebt($card));
    }

    public function test_installments_use_whole_purchase_value_against_limit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);

        $this->purchase($user, $card, 'R$ 1.200,00', 12)->assertHasErrors(['amount']);
        $this->assertDatabaseCount('credit_card_installments', 0);

        $this->purchase($user, $card, 'R$ 600,00', 6)->assertHasNoErrors();
        $this->assertSame(60000, (new CreditCardLedger())->totalDebt($card));

        $this->purchase($user, $card, 'R$ 450,00', 3)
            ->assertHasErrors(['amount'])
            ->assertSee('R$ 400,00');
        $this->assertDatabaseCount('credit_card_purchases', 1);

        $this->purchase($user, $card, 'R$ 400,00', 4)->assertHasNoErrors();
        $this->assertSame(100000, (new CreditCardLedger())->totalDebt($card));

        $this->purchase($user, $card, 'R$ 0,01')
            ->assertHasErrors(['amount'])
            ->assertSee('Disponível: R$ 0,00');
        $this->assertDatabaseCount('credit_card_purchases', 2);
    }

    public function test_full_credit_limit_updates_as_card_invoice_is_paid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);
        $bank = FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Itaú',
            'type' => 'corrente',
            'initial_balance' => '1000.00',
            'active' => true,
        ]);

        $this->purchase($user, $card, 'R$ 900,00')->assertHasNoErrors();
        $invoice = $card->invoices()->firstOrFail();

        $this->purchase($user, $card, 'R$ 150,00')->assertHasErrors(['amount']);

        app(PayCreditCardInvoice::class)->execute(
            $user->id, $invoice->id, $bank->id, 20000, '2026-11-15'
        );

        $this->assertSame(70000, (new CreditCardLedger())->totalDebt($card));
        $this->assertSame(80000, (new FinancialBalance())->currentCents($bank));

        $this->purchase($user, $card, 'R$ 300,00')->assertHasNoErrors();
        $this->purchase($user, $card, 'R$ 0,01')->assertHasErrors(['amount']);

        $this->assertSame(100000, (new CreditCardLedger())->totalDebt($card));
        $this->assertSame(80000, (new FinancialBalance())->currentCents($bank));
    }

    public function test_existing_over_limit_purchase_is_preserved_but_new_purchases_are_blocked(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user, null);

        $this->purchase($user, $card, 'R$ 1.200,00')->assertHasNoErrors();
        $card->update(['credit_limit' => '1000.00']);

        $this->purchase($user, $card, 'R$ 10,00')
            ->assertHasErrors(['amount'])
            ->assertSee('Disponível: R$ 0,00');

        $this->assertDatabaseCount('credit_card_purchases', 1);
        $this->assertSame(120000, (new CreditCardLedger())->totalDebt($card));
    }

    public function test_server_side_limit_check_cannot_be_bypassed_without_livewire(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);

        try {
            app(RecordCreditCardPurchase::class)->execute(
                $user->id, $card->id, '2026-11-14', 'Compra direta',
                'compras', 120000, 10
            );
            $this->fail('Service must enforce the credit limit');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertDatabaseCount('credit_card_purchases', 0);
    }

    public function test_form_shows_available_credit_for_selected_card(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->assertViewHas('selectedCardAvailableCents', 100000)
            ->assertSee('Limite disponível')
            ->assertSee('R$ 1.000,00');

        $this->purchase($user, $card, 'R$ 700,00')->assertHasNoErrors();

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->assertViewHas('selectedCardAvailableCents', 30000)
            ->assertSee('R$ 300,00');
    }

    public function test_invoice_modal_does_not_automatically_mark_open_invoice_as_overdue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user, '1000.00', 'Hiper');
        $card->update(['closing_day' => 9, 'due_day' => 15]);

        $this->purchase($user, $card, 'R$ 250,00')->assertHasNoErrors();
        $invoice = $card->invoices()->firstOrFail();

        // Inclui o dia exato e datas após o vencimento.
        foreach (['2026-12-15 12:00', '2026-12-17 12:00'] as $time) {
            $this->travelTo(CarbonImmutable::parse($time));

            Livewire::actingAs($user)->test(CreditCards::class)
                ->call('selectCard', $card->id)
                ->call('selectInvoice', $invoice->id)
                ->assertSee('Em aberto')
                ->assertDontSee('Vencida');
        }
        $this->assertDatabaseCount('credit_card_invoice_payments', 0);
    }
}
