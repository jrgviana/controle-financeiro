<?php

namespace Tests\Feature;

use App\Models\AccountTransfer;
use App\Models\CreditCard;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ReceivedIncome;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use App\Models\User;
use App\Services\PayCreditCardInvoice;
use App\Services\PayRecurringMonthlyBill;
use App\Services\RecordCreditCardPurchase;
use App\Support\DashboardOverview;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $name = 'Itaú', string $initial = '0.00'): FinancialAccount
    {
        return FinancialAccount::create([
            'user_id' => $user->id, 'name' => $name, 'type' => 'corrente',
            'initial_balance' => $initial, 'active' => true,
        ]);
    }

    private function income(User $user, FinancialAccount $account, string $value, string $date = '2026-11-15'): void
    {
        ReceivedIncome::create([
            'user_id' => $user->id, 'financial_account_id' => $account->id,
            'received_on' => $date, 'description' => 'Salário confirmado',
            'category' => 'salario', 'amount' => $value,
        ]);
    }

    private function spent(User $user, FinancialAccount $account, string $value, string $date = '2026-11-15'): void
    {
        DailyExpense::create([
            'user_id' => $user->id, 'financial_account_id' => $account->id,
            'paid_on' => $date, 'description' => 'Mercado',
            'category' => 'alimentacao', 'amount' => $value,
        ]);
    }

    public function test_dashboard_is_private_and_displays_rich_empty_state_without_errors(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));

        $this->get('/dashboard')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Painel financeiro')
            ->assertSee('Movimentação nos últimos 6 meses')
            ->assertSee('Onde seu dinheiro foi gasto')
            ->assertSee('Próximos pagamentos e pendências')
            ->assertSee('Situação dos cartões')
            ->assertSee('Saldos por conta')
            ->assertSee('Contas mensais a confirmar')
            ->assertSee('Últimas movimentações')
            ->assertSee('R$ 0,00');

        $overview = app(DashboardOverview::class)->build($user->id, CarbonImmutable::parse('2026-11-01'));
        $this->assertSame(0, $overview['balance_cents']);
        $this->assertSame(0, $overview['pending_cents']);
        $this->assertCount(6, $overview['trend']);
    }

    public function test_monthly_dashboard_shows_cash_not_double_counting_card_purchases_or_transfers(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, 'Itaú', '500.00');
        $other = $this->account($user, 'Neon');
        $this->income($user, $bank, '1000.00');
        $this->spent($user, $bank, '120.00');

        AccountTransfer::create([
            'user_id' => $user->id, 'from_account_id' => $bank->id,
            'to_account_id' => $other->id, 'transferred_on' => '2026-11-15',
            'amount' => '200.00',
        ]);

        $card = CreditCard::create([
            'user_id' => $user->id, 'name' => 'Platinum',
            'credit_limit' => '2000.00', 'closing_day' => 9, 'due_day' => 15,
            'active' => true,
        ]);
        app(RecordCreditCardPurchase::class)->execute(
            $user->id, $card->id, '2026-11-14', 'Compra parcelada',
            'compras', 120000, 10
        );

        $overview = app(DashboardOverview::class)->build($user->id, CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(138000, $overview['balance_cents']);
        $this->assertSame(100000, $overview['income_cents']);
        $this->assertSame(12000, $overview['paid_cents']);
        $this->assertSame(88000, $overview['cash_difference_cents']);
        $this->assertSame(120000, $overview['card_debt_cents']);
        $this->assertSame(120000, $overview['card_purchases_cents']);
        $this->assertSame(120000, $overview['card_categories']->first()['cents']);
        $this->assertSame('Compras', $overview['card_categories']->first()['name']);
        $this->assertSame(100, $overview['card_categories']->first()['percent']);
        $this->assertSame(1, $overview['card_categories']->first()['count']);
        $this->assertSame('Compra parcelada', $overview['recent_card_purchases']->first()->description);
        $this->assertSame(80000, $overview['cards']->first()['remaining_cents']);
        $this->assertSame(60, $overview['cards']->first()['usage_pct']);
        $this->assertSame(0, $overview['pending_cents']);
        $this->assertSame(12000, $overview['expense_categories_cents']);
        $this->assertSame('Alimentação', $overview['categories']->first()['name']);
        $this->assertSame(2, $overview['accounts_count']);
        $this->assertSame(1, $overview['card_count']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()->assertSee('R$ 1.380,00')->assertSee('R$ 1.000,00')
            ->assertSee('R$ 120,00')->assertSee('R$ 1.200,00')
            ->assertSee('Platinum')->assertSee('R$ 800,00')
            ->assertSee('Compras no cartão neste mês');
    }

    public function test_dashboard_shows_exact_destinations_of_card_purchases_without_mixing_paid_expenses(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, 'Itaú', '2500.00');
        $this->spent($user, $bank, '150.00');

        $card = CreditCard::create([
            'user_id' => $user->id,
            'name' => 'Cartão Passaí',
            'credit_limit' => '2500.00',
            'closing_day' => 7,
            'due_day' => 13,
            'active' => true,
        ]);

        // A compra inteira entra na categoria no mês da COMPRA. A quantidade
        // de parcelas não multiplica nem reduz o valor desta análise.
        app(RecordCreditCardPurchase::class)->execute(
            $user->id, $card->id, '2026-11-12', 'Pneus do carro',
            'transporte', 120000, 10
        );
        app(RecordCreditCardPurchase::class)->execute(
            $user->id, $card->id, '2026-11-14', 'Farmácia',
            'saude', 30000, 1
        );

        $overview = app(DashboardOverview::class)
            ->build($user->id, CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(235000, $overview['balance_cents']);
        $this->assertSame(15000, $overview['paid_cents']);
        $this->assertSame(15000, $overview['expense_categories_cents']);
        $this->assertSame('Alimentação', $overview['categories']->first()['name']);
        $this->assertCount(1, $overview['categories']);

        $this->assertSame(150000, $overview['card_purchases_cents']);
        $this->assertSame(2, $overview['card_purchases_count']);
        $this->assertCount(2, $overview['card_categories']);
        $this->assertSame('Transporte', $overview['card_categories'][0]['name']);
        $this->assertSame(120000, $overview['card_categories'][0]['cents']);
        $this->assertSame(80, $overview['card_categories'][0]['percent']);
        $this->assertSame('Saúde', $overview['card_categories'][1]['name']);
        $this->assertSame(30000, $overview['card_categories'][1]['cents']);
        $this->assertSame(20, $overview['card_categories'][1]['percent']);
        $this->assertSame(150000, $overview['card_debt_cents']);
        $this->assertSame('Farmácia', $overview['recent_card_purchases'][0]->description);
        $this->assertSame('Cartão Passaí', $overview['recent_card_purchases'][0]->card->name);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Pagamentos diretos')
            ->assertSee('Compras no cartão')
            ->assertSee('Gastos já pagos pelas contas')
            ->assertSee('R$ 1.500,00')
            ->assertSee('Transporte')
            ->assertSee('Saúde')
            ->assertSee('Pneus do carro')
            ->assertSee('Farmácia')
            ->assertSee('Cartão Passaí');
    }

    public function test_card_category_data_respects_month_and_user_scopes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $card = CreditCard::create([
            'user_id' => $owner->id,
            'name' => 'Cartão particular',
            'credit_limit' => '1000.00',
            'closing_day' => 9,
            'due_day' => 15,
            'active' => true,
        ]);

        app(RecordCreditCardPurchase::class)->execute(
            $owner->id, $card->id, '2026-10-08', 'Compra de outubro',
            'lazer', 12000, 2
        );
        app(RecordCreditCardPurchase::class)->execute(
            $owner->id, $card->id, '2026-11-11', 'Compra de novembro',
            'educacao', 24000, 3
        );

        $october = app(DashboardOverview::class)
            ->build($owner->id, CarbonImmutable::parse('2026-10-01'));
        $november = app(DashboardOverview::class)
            ->build($owner->id, CarbonImmutable::parse('2026-11-01'));
        $other = app(DashboardOverview::class)
            ->build($viewer->id, CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(12000, $october['card_purchases_cents']);
        $this->assertSame('Lazer', $october['card_categories']->first()['name']);
        $this->assertSame(24000, $november['card_purchases_cents']);
        $this->assertSame('Educação', $november['card_categories']->first()['name']);
        $this->assertSame(0, $other['card_purchases_cents']);
        $this->assertCount(0, $other['card_categories']);
        $this->assertCount(0, $other['recent_card_purchases']);
        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Cartão particular')
            ->assertDontSee('Compra de novembro');
    }

    public function test_unconfirmed_monthly_forecast_does_not_create_a_payable_until_confirmed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, 'Itaú', '1000.00');

        $plan = RecurringBill::create([
            'user_id' => $user->id, 'description' => 'Energia',
            'category' => 'moradia', 'due_day' => 17,
            'planned_amount' => '316.75', 'starts_on' => '2026-11-01',
            'active' => true,
        ]);

        $month = CarbonImmutable::parse('2026-11-01');
        $before = app(DashboardOverview::class)->build($user->id, $month);
        $this->assertSame(31675, $before['forecast_cents']);
        $this->assertSame(1, $before['forecast_count']);
        $this->assertSame(0, $before['pending_cents']);

        ScheduledBill::create([
            'user_id' => $user->id, 'recurring_bill_id' => $plan->id,
            'period_month' => '2026-11-01', 'description' => 'Energia',
            'category' => 'moradia', 'amount' => '325.40',
            'due_on' => '2026-11-17',
        ]);

        $confirmed = app(DashboardOverview::class)->build($user->id, $month);
        $this->assertSame(0, $confirmed['forecast_cents']);
        $this->assertSame(32540, $confirmed['pending_cents']);
        $this->assertSame(0, $confirmed['paid_cents']);

        app(PayRecurringMonthlyBill::class)->execute(
            $user->id, $plan->id, '2026-11', 32540, $bank->id, '2026-11-15'
        );

        $paid = app(DashboardOverview::class)->build($user->id, $month);
        $this->assertSame(0, $paid['pending_cents']);
        $this->assertSame(0, $paid['forecast_cents']);
        $this->assertSame(32540, $paid['paid_cents']);
        $this->assertSame(67460, $paid['balance_cents']);

        $december = app(DashboardOverview::class)->build($user->id, $month->addMonth());
        $this->assertSame(0, $december['pending_cents']);
        $this->assertSame(31675, $december['forecast_cents']);
    }

    public function test_card_invoice_payment_reduces_available_cash_and_outstanding_card_debt_only_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, 'Itaú', '700.00');
        $card = CreditCard::create([
            'user_id' => $user->id, 'name' => 'Hiper',
            'credit_limit' => '1000.00', 'closing_day' => 9, 'due_day' => 15,
            'active' => true,
        ]);
        app(RecordCreditCardPurchase::class)->execute(
            $user->id, $card->id, '2026-11-14', 'Compra',
            'compras', 30000, 1
        );
        $invoice = $card->invoices()->firstOrFail();
        app(PayCreditCardInvoice::class)->execute(
            $user->id, $invoice->id, $bank->id, 10000, '2026-11-15'
        );

        $overview = app(DashboardOverview::class)->build($user->id, CarbonImmutable::parse('2026-11-01'));
        $this->assertSame(60000, $overview['balance_cents']);
        $this->assertSame(10000, $overview['paid_cents']);
        $this->assertSame(20000, $overview['card_debt_cents']);
        $this->assertSame(30000, $overview['card_purchases_cents']);
        $this->assertSame(30000, $overview['card_categories']->first()['cents']);
        $this->assertSame(100, $overview['card_categories']->first()['percent']);
        $this->assertSame(0, $overview['income_cents']);
        $this->assertSame(0, $overview['expense_categories_cents']);
    }

    public function test_month_filter_and_six_month_history_do_not_change_today_bank_balance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $user = User::factory()->create();
        $bank = $this->account($user, 'Principal');
        $this->income($user, $bank, '500.00', '2026-10-12');
        $this->income($user, $bank, '1000.00', '2026-11-12');
        $this->spent($user, $bank, '200.00', '2026-10-15');

        $october = app(DashboardOverview::class)->build($user->id, CarbonImmutable::parse('2026-10-01'));
        $november = app(DashboardOverview::class)->build($user->id, CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(130000, $october['balance_cents']);
        $this->assertSame(130000, $november['balance_cents']);
        $this->assertSame(50000, $october['income_cents']);
        $this->assertSame(20000, $october['paid_cents']);
        $this->assertSame(100000, $november['income_cents']);
        $this->assertSame(0, $november['paid_cents']);
        $this->assertSame('2026-05', $october['trend']->first()['period']);
        $this->assertSame('2026-10', $october['trend']->last()['period']);

        $this->actingAs($user)->get('/dashboard?period=2026-10')
            ->assertOk()->assertSee('Outubro de 2026')->assertSee('R$ 1.300,00')
            ->assertSee('R$ 500,00')->assertSee('R$ 200,00');

        // Período inválido ou distante não força leitura ilimitada de recorrências.
        $this->actingAs($user)->get('/dashboard?period=2100-01')
            ->assertOk()->assertSee('Novembro de 2026');
    }

    public function test_dashboard_is_isolated_by_authenticated_user(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $bank = $this->account($owner, 'Banco privado', '12345.67');
        $this->income($owner, $bank, '9876.54');

        $overview = app(DashboardOverview::class)->build($viewer->id, CarbonImmutable::parse('2026-11-01'));
        $this->assertSame(0, $overview['balance_cents']);
        $this->assertSame(0, $overview['income_cents']);
        $this->assertSame(0, $overview['accounts_count']);

        $this->actingAs($viewer)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Banco privado')
            ->assertDontSee('12.345,67')
            ->assertDontSee('9.876,54');
    }

    public function test_global_navigation_has_accessible_mobile_menu_and_all_existing_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')->assertOk()
            ->assertSee('finance-mobile-menu')
            ->assertSee('Menu do aplicativo')
            ->assertSee('href="'.route('pagamentos.index').'"', false)
            ->assertSee('href="'.route('contas-fixas.index').'"', false)
            ->assertSee('href="'.route('cartoes.index').'"', false)
            ->assertSee('viewport-fit=cover');
    }
}
