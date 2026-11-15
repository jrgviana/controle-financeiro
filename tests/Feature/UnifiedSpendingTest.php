<?php

namespace Tests\Feature;

use App\Livewire\CreditCards;
use App\Livewire\DailyExpenses;
use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnifiedSpendingTest extends TestCase
{
    use RefreshDatabase;

    private function bank(User $user): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id,
            'name' => 'Itaú',
            'type' => 'corrente',
            'initial_balance' => '1000.00',
            'active' => true,
        ]);
    }

    private function card(User $user, string $name, int $closing, int $due, bool $active = true): CreditCard
    {
        return CreditCard::create([
            'user_id' => $user->id,
            'name' => $name,
            'closing_day' => $closing,
            'due_day' => $due,
            'active' => $active,
        ]);
    }

    private function cardPurchase(User $user, CreditCard $card, string $description, string $value, int $count = 1)
    {
        return Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('paymentMethod', 'card')
            ->set('cardId', (string) $card->id)
            ->set('description', $description)
            ->set('category', 'compras')
            ->set('amount', $value)
            ->set('installmentsCount', $count)
            ->set('paidOn', '2026-11-11')
            ->call('save');
    }

    public function test_spending_page_exposes_both_methods_and_card_page_has_no_purchase_form(): void
    {
        $user = User::factory()->create();
        $this->bank($user);
        $this->card($user, 'Neon', 10, 15);

        $this->actingAs($user)
            ->get('/gastos')
            ->assertOk()
            ->assertSee('Pagamento imediato')
            ->assertSee('Compra no cartão')
            ->assertSee('Gastos')
            ->assertDontSee('overflow-x-auto');

        $this->actingAs($user)
            ->get('/cartoes')
            ->assertOk()
            ->assertSee('Cartões de crédito')
            ->assertDontSee('wire:submit="savePurchase"', false)
            ->assertDontSee('Lançar compra')
            ->assertDontSee('overflow-x-auto');
    }

    public function test_account_spending_remains_paid_and_card_spending_creates_installments_without_double_debit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $card = $this->card($user, 'Cartão Itaú', 10, 15);

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->set('description', 'Café')
            ->set('accountId', (string) $bank->id)
            ->set('amount', 'R$ 15,00')
            ->call('save')
            ->assertHasNoErrors();

        $this->cardPurchase($user, $card, 'Celular', 'R$ 600,00', 3)
            ->assertHasNoErrors()
            ->assertSet('paymentMethod', 'card')
            ->assertSet('cardId', (string) $card->id)
            ->assertSee('Celular')
            ->assertSee('Parcelas nas faturas');

        $this->assertDatabaseCount('daily_expenses', 1);
        $this->assertDatabaseCount('credit_card_purchases', 1);
        $this->assertDatabaseCount('credit_card_installments', 3);
        $this->assertSame(98500, (new FinancialBalance())->currentCents($bank));
        $this->assertSame(60000, (new CreditCardLedger())->totalDebt($card));
    }

    public function test_user_can_choose_any_active_card_and_each_uses_its_own_billing_cycle(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $first = $this->card($user, 'Cartão A', 10, 15);
        $second = $this->card($user, 'Cartão B', 30, 7);

        $this->cardPurchase($user, $first, 'Compra A', 'R$ 50,00')->assertHasNoErrors();
        $this->cardPurchase($user, $second, 'Compra B', 'R$ 80,00')->assertHasNoErrors();

        $purchases = CreditCardPurchase::query()->orderBy('id')->get();
        $this->assertSame([$first->id, $second->id], $purchases->pluck('credit_card_id')->all());
        $this->assertSame('2026-12-15', $purchases[0]->installments()->firstOrFail()->invoice->due_on->toDateString());
        $this->assertSame('2026-12-07', $purchases[1]->installments()->firstOrFail()->invoice->due_on->toDateString());
    }

    public function test_cash_card_purchase_is_one_of_one_in_selected_invoice_and_visible_in_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user, 'Neon', 10, 15);

        $this->cardPurchase($user, $card, 'Combustível', 'R$ 90,00')->assertHasNoErrors();

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->assertSee('Combustível')
            ->assertSee('À vista · 1 de 1')
            ->assertSee('Neon')
            ->assertSee('Ver faturas');

        Livewire::actingAs($user)->test(CreditCards::class)
            ->call('selectCard', $card->id)
            ->assertSet('invoiceModalOpen', true)
            ->assertSee('Parcela 1 de 1')
            ->assertSee('Combustível');
    }

    public function test_card_spending_does_not_require_bank_account_to_exist(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user, 'Cartão sem conta', 10, 15);

        $this->cardPurchase($user, $card, 'Compra', 'R$ 40,00')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('credit_card_purchases', 1);
    }

    public function test_foreign_or_inactive_cards_cannot_receive_purchases(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));

        $user = User::factory()->create();
        $owner = User::factory()->create();
        $other = $this->card($owner, 'Cartão privado', 10, 15);
        $inactive = $this->card($user, 'Cartão inativo', 10, 15, false);
        $this->card($user, 'Cartão ativo', 10, 15);

        $this->cardPurchase($user, $other, 'Compra privada', 'R$ 15,00')->assertHasErrors(['cardId']);
        $this->cardPurchase($user, $inactive, 'Compra inativa', 'R$ 15,00')->assertHasErrors(['cardId']);

        $this->assertDatabaseCount('credit_card_purchases', 0);
    }

    public function test_one_cent_purchase_cannot_be_split_into_multiple_zero_value_installments(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $card = $this->card($user, 'Neon', 10, 15);

        $this->cardPurchase($user, $card, 'Teste', 'R$ 0,01', 2)
            ->assertHasErrors(['installmentsCount']);

        $this->assertDatabaseCount('credit_card_purchases', 0);
        $this->assertDatabaseCount('credit_card_installments', 0);
    }

    public function test_combined_history_lists_account_and_card_purchases_in_same_month_without_mixing_balances(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00'));
        $user = User::factory()->create();
        $bank = $this->bank($user);
        $card = $this->card($user, 'Mastercard', 10, 15);

        DailyExpense::create([
            'user_id' => $user->id,
            'financial_account_id' => $bank->id,
            'description' => 'Mercado pago',
            'category' => 'alimentacao',
            'paid_on' => '2026-11-11',
            'amount' => '50.00',
        ]);

        $this->cardPurchase($user, $card, 'Compra parcelada', 'R$ 120,00', 4)->assertHasNoErrors();

        Livewire::actingAs($user)->test(DailyExpenses::class)
            ->assertSee('Mercado pago')
            ->assertSee('Compra parcelada')
            ->assertSee('Pagamentos efetivados no mês')
            ->assertSee('Compras feitas no cartão no mês')
            ->assertSee('R$ 50,00')
            ->assertSee('R$ 120,00');
    }
}
