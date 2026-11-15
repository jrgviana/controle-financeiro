@extends('layouts.app')

@section('title', 'Painel financeiro')

@section('content')
@php
    $money = static fn (int $cents): string => 'R$ '.\App\Support\FinancialBalance::formatted($cents);
@endphp

<div class="finance-dashboard space-y-6 sm:space-y-7">
    <section class="finance-hero grid items-center gap-7 p-6 sm:p-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(260px,.75fr)] lg:p-9">
        <div class="min-w-0">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-indigo-50">
                <span class="size-2 rounded-full bg-teal-300" aria-hidden="true"></span>
                Painel financeiro
            </div>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">
                Olá, {{ auth()->user()->name }}!
            </h1>
            <p class="mt-3 max-w-lg text-sm leading-7 text-indigo-100 sm:text-base">
                Seu dinheiro, suas contas e seus próximos compromissos em uma única visão.
            </p>
            <div class="mt-6 flex flex-wrap gap-2">
                <a href="{{ route('pagamentos.index') }}" class="finance-action-secondary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold">
                    <span aria-hidden="true">✓</span> Realizar pagamentos
                </a>
                <a href="{{ route('gastos.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    <span aria-hidden="true">↗</span> Lançar gasto
                </a>
                <a href="{{ route('recebimentos.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/20">
                    <span aria-hidden="true">↙</span> Registrar entrada
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-white/20 bg-white/12 p-5 backdrop-blur-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-semibold text-indigo-100">Disponível nas contas</span>
                <span class="rounded-full bg-teal-300/20 px-2.5 py-1 text-[11px] font-bold text-teal-100">Saldo de hoje</span>
            </div>
            <p class="mt-4 break-words text-3xl font-extrabold tracking-tight tabular-nums sm:text-4xl">{{ $money($dashboard['balance_cents']) }}</p>
            <p class="mt-2 text-xs leading-5 text-indigo-100">Saldos positivos das contas ativas, depois de pagamentos e transferências.</p>
            <a href="{{ route('contas.index') }}" class="mt-5 inline-flex min-h-9 items-center gap-1.5 border-b border-indigo-100/70 pb-1 text-xs font-semibold text-white hover:border-white">
                Consultar saldos <span aria-hidden="true">↗</span>
            </a>
        </div>
    </section>

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="finance-overline text-indigo-600">Visão consolidada</p>
            <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-800 sm:text-2xl">{{ $dashboard['month_label'] }}</h2>
            <p class="mt-1 text-xs text-slate-500">Valores do período selecionado, exceto saldos atuais e dívida total nos cartões.</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-end gap-2">
            <div>
                <label for="dashboard-month" class="mb-1.5 block text-xs font-bold text-slate-600">Competência</label>
                <select id="dashboard-month" name="period" class="min-h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm">
                    @foreach ($periodOptions as $period)
                        <option value="{{ $period['value'] }}" @selected($selectedMonth === $period['value'])>{{ $period['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="finance-action-primary inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold text-white">Ver mês</button>
        </form>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Principais indicadores do mês">
        <article class="finance-stat-card">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold text-slate-600">Recebimentos</span>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-xl font-semibold text-emerald-700" aria-hidden="true">↙</span>
            </div>
            <p class="finance-stat-value mt-4 text-emerald-700">{{ $money($dashboard['income_cents']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Entradas que realmente foram recebidas.</p>
        </article>
        <article class="finance-stat-card">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold text-slate-600">Pagamentos realizados</span>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-xl font-semibold text-rose-600" aria-hidden="true">↗</span>
            </div>
            <p class="finance-stat-value mt-4 text-slate-900">{{ $money($dashboard['paid_cents']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Gastos pagos e faturas efetivamente quitadas.</p>
        </article>
        <article class="finance-stat-card">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold text-slate-600">Compromissos para pagar</span>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-xl font-semibold text-amber-700" aria-hidden="true">⌁</span>
            </div>
            <p class="finance-stat-value mt-4 text-slate-900">{{ $money($dashboard['pending_cents']) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $dashboard['pending_count'] }} pendência(s) confirmada(s), até este mês.</p>
        </article>
        <article class="finance-stat-card">
            <div class="flex items-start justify-between gap-3">
                <span class="text-sm font-semibold text-slate-600">Dívida nos cartões</span>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xl font-semibold text-indigo-700" aria-hidden="true">▣</span>
            </div>
            <p class="finance-stat-value mt-4 text-indigo-700">{{ $money($dashboard['card_debt_cents']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Todas as parcelas em aberto, inclusive futuras.</p>
        </article>
    </section>

    <section class="grid gap-3 md:grid-cols-3" aria-label="Informações de planejamento">
        <article class="finance-panel flex items-center gap-4 p-4 sm:p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-xl text-teal-700" aria-hidden="true">◈</span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500">{{ $dashboard['cash_difference_positive'] ? 'Recebimentos menos pagamentos' : 'Pagamentos acima dos recebimentos' }}</p>
                <p class="mt-1 break-words text-xl font-bold tabular-nums {{ $dashboard['cash_difference_positive'] ? 'text-teal-700' : 'text-amber-700' }}">{{ $money($dashboard['cash_difference_cents']) }}</p>
            </div>
        </article>
        <article class="finance-panel flex items-center gap-4 p-4 sm:p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-xl text-rose-600" aria-hidden="true">!</span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500">Compromissos vencidos e pendentes</p>
                <p class="mt-1 break-words text-xl font-bold tabular-nums text-slate-800">{{ $money($dashboard['overdue_cents']) }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ $dashboard['overdue_count'] }} registro(s)</p>
            </div>
        </article>
        <article class="finance-panel flex items-center gap-4 p-4 sm:p-5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-xl text-indigo-700" aria-hidden="true">◎</span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500">Compras no cartão neste mês</p>
                <p class="mt-1 break-words text-xl font-bold tabular-nums text-slate-800">{{ $money($dashboard['card_purchases_cents']) }}</p>
                <p class="mt-0.5 text-xs text-slate-500">Não debitadas da conta até o pagamento.</p>
            </div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Movimentação nos últimos 6 meses</h2>
                    <p class="finance-subtitle mt-1">Entradas recebidas e saídas pagas, sem transferências internas.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600">
                    <span class="flex items-center gap-1.5"><i class="size-2.5 rounded-full bg-teal-500"></i> Recebido</span>
                    <span class="flex items-center gap-1.5"><i class="size-2.5 rounded-full bg-indigo-500"></i> Pago</span>
                </div>
            </div>
            <div class="mt-8 grid grid-cols-6 gap-1.5 sm:gap-4" role="img" aria-label="Gráfico comparativo de entradas e pagamentos de seis meses">
                @foreach ($dashboard['trend'] as $entry)
                    <div class="min-w-0 text-center">
                        <div class="flex h-40 items-end justify-center gap-1 border-b border-slate-200 px-0.5 sm:h-44 sm:gap-2">
                            <div class="finance-chart-bar w-3 bg-teal-500 sm:w-5" style="height: {{ $entry['income_cents'] > 0 ? max(3, $entry['income_pct']) : 0 }}%"
                                title="{{ $entry['label'] }} · Recebido: {{ $money($entry['income_cents']) }}"></div>
                            <div class="finance-chart-bar w-3 bg-indigo-500 sm:w-5" style="height: {{ $entry['paid_cents'] > 0 ? max(3, $entry['paid_pct']) : 0 }}%"
                                title="{{ $entry['label'] }} · Pago: {{ $money($entry['paid_cents']) }}"></div>
                        </div>
                        <p class="mt-3 truncate text-[11px] font-semibold text-slate-500 sm:text-xs">{{ $entry['label'] }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                Diferença do mês:
                <strong class="{{ $dashboard['cash_difference_positive'] ? 'text-teal-700' : 'text-amber-700' }}">
                    {{ $dashboard['cash_difference_positive'] ? 'Recebido a mais' : 'Pagamentos acima das entradas' }} · {{ $money($dashboard['cash_difference_cents']) }}
                </strong>
            </p>
        </article>

        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Onde seu dinheiro foi gasto</h2>
                    <p class="finance-subtitle mt-1">Despesas pagas por categoria, sem repetir faturas dos cartões.</p>
                </div>
                <span class="finance-small-tag bg-indigo-50 text-indigo-700">{{ $money($dashboard['expense_categories_cents']) }}</span>
            </div>
            @if ($dashboard['categories']->isNotEmpty())
                <div class="mt-6 space-y-4">
                    @foreach ($dashboard['categories'] as $category)
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                                <span class="flex min-w-0 items-center gap-2 font-semibold text-slate-700">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $category['color'] }}"></span>
                                    <span class="truncate">{{ $category['name'] }}</span>
                                </span>
                                <span class="shrink-0 font-bold tabular-nums text-slate-800">{{ $money($category['cents']) }}</span>
                            </div>
                            <div class="finance-account-progress">
                                <span style="width: {{ max(1, $category['percent']) }}%; background: {{ $category['color'] }}"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="finance-empty mt-6">Sem gastos pagos classificados neste período.</div>
            @endif
            <a href="{{ route('gastos.index') }}" class="mt-6 inline-flex text-xs font-bold text-indigo-700 hover:underline">Consultar meus gastos ↗</a>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Próximos pagamentos e pendências</h2>
                    <p class="finance-subtitle mt-1">Apenas cobranças confirmadas que ainda precisam ser pagas.</p>
                </div>
                <a href="{{ route('pagamentos.index') }}" class="text-xs font-bold text-indigo-700 hover:underline">Abrir pagamentos ↗</a>
            </div>
            @if ($dashboard['overdue_count'] > 0 || $dashboard['upcoming_week_count'] > 0)
                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($dashboard['overdue_count'] > 0)
                        <span class="finance-small-tag bg-rose-50 text-rose-700">{{ $dashboard['overdue_count'] }} vencido(s)</span>
                    @endif
                    @if ($dashboard['upcoming_week_count'] > 0)
                        <span class="finance-small-tag bg-amber-50 text-amber-700">{{ $dashboard['upcoming_week_count'] }} nos próximos 7 dias</span>
                    @endif
                </div>
            @endif
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($dashboard['next_payments'] as $payment)
                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-semibold text-slate-800">{{ $payment['description'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Vence {{ $payment['due_on']->format('d/m/Y') }} · {{ $payment['detail'] }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="font-bold tabular-nums text-slate-900">{{ $money($payment['amount_cents']) }}</span>
                            <span class="finance-small-tag {{ $payment['status'] === 'Vencida' ? 'bg-rose-50 text-rose-700' : ($payment['status'] === 'Parcial' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $payment['status'] }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="finance-empty">Nenhuma cobrança confirmada aguardando pagamento.</div>
                @endforelse
            </div>
            @if ($dashboard['postponed_count'] > 0)
                <p class="mt-3 text-xs text-amber-700">{{ $dashboard['postponed_count'] }} compromisso(s) marcado(s) como não pago(s) no mês. Eles continuam no próximo período.</p>
            @endif
        </article>

        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Capacidade de pagamento</h2>
                    <p class="finance-subtitle mt-1">Disponível hoje comparado às pendências confirmadas.</p>
                </div>
            </div>
            <div class="mt-5 space-y-3 rounded-2xl bg-slate-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-slate-600">Dinheiro disponível</span>
                    <strong class="tabular-nums text-slate-900">{{ $money($dashboard['balance_cents']) }}</strong>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-slate-600">Compromissos pendentes</span>
                    <strong class="tabular-nums text-slate-900">{{ $money($dashboard['pending_cents']) }}</strong>
                </div>
                <div class="border-t border-slate-200 pt-3">
                    <p class="text-xs text-slate-500">
                        {{ $dashboard['missing_cents'] > 0 ? 'Faltaria para quitar todas as pendências' : 'Restaria após quitar todas as pendências' }}
                    </p>
                    <p class="mt-1 break-words text-2xl font-extrabold tabular-nums {{ $dashboard['missing_cents'] > 0 ? 'text-amber-700' : 'text-teal-700' }}">
                        {{ $money($dashboard['missing_cents'] > 0 ? $dashboard['missing_cents'] : $dashboard['coverage_cents']) }}
                    </p>
                </div>
            </div>
            <p class="mt-4 text-xs leading-5 text-slate-500">É uma comparação para planejamento. Os valores só saem do banco quando você confirma o pagamento.</p>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <div class="rounded-xl border border-slate-100 p-3">
                    <p class="text-xs text-slate-500">Até 7 dias</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-800">{{ $money($dashboard['upcoming_week_cents']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-100 p-3">
                    <p class="text-xs text-slate-500">Vencidos</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-800">{{ $money($dashboard['overdue_cents']) }}</p>
                </div>
            </div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Saldos por conta</h2>
                    <p class="finance-subtitle mt-1">{{ $dashboard['accounts_count'] }} conta(s) ativa(s) · Saldos reais de hoje</p>
                </div>
                <a href="{{ route('contas.index') }}" class="text-xs font-bold text-indigo-700 hover:underline">Minhas contas ↗</a>
            </div>
            <div class="mt-5 divide-y divide-slate-100">
                @forelse ($dashboard['accounts']->take(6) as $account)
                    <div class="flex min-w-0 items-center justify-between gap-3 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-lg font-semibold text-indigo-700" aria-hidden="true">▤</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $account['name'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $account['active'] ? 'Conta ativa' : 'Conta inativa' }}</p>
                            </div>
                        </div>
                        <p class="shrink-0 text-sm font-bold tabular-nums text-slate-800">
                            {{ $account['balance_cents'] < 0 ? 'Faltam '.$money($account['balance_cents']) : $money($account['balance_cents']) }}
                        </p>
                    </div>
                @empty
                    <div class="finance-empty">Nenhuma conta cadastrada.</div>
                @endforelse
            </div>
        </article>

        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Situação dos cartões</h2>
                    <p class="finance-subtitle mt-1">{{ $dashboard['card_count'] }} cartão(ões) ativo(s) · Compromisso total</p>
                </div>
                <a href="{{ route('cartoes.index') }}" class="text-xs font-bold text-indigo-700 hover:underline">Ver cartões ↗</a>
            </div>
            <div class="mt-5 space-y-4">
                @forelse ($dashboard['cards']->take(5) as $card)
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="font-bold text-slate-800">{{ $card['name'] }}</span>
                            <span class="font-bold tabular-nums text-indigo-700">{{ $money($card['debt_cents']) }} em aberto</span>
                        </div>
                        @if ($card['limit_cents'] !== null)
                            <div class="finance-account-progress mt-3">
                                <span style="width: {{ $card['usage_pct'] }}%; background: {{ $card['usage_pct'] >= 85 ? '#d97788' : '#5867d8' }}"></span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                <span>Limite: {{ $money($card['limit_cents']) }}</span>
                                <span>Disponível: {{ $money($card['remaining_cents']) }}</span>
                            </div>
                        @else
                            <p class="mt-2 text-xs text-slate-500">Limite não informado</p>
                        @endif
                    </div>
                @empty
                    <div class="finance-empty">Nenhum cartão cadastrado.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <article class="finance-panel p-5 sm:p-6">
            <div class="finance-section-head">
                <div>
                    <h2 class="finance-section-title">Últimas movimentações</h2>
                    <p class="finance-subtitle mt-1">Entradas, pagamentos e transferências registrados no mês.</p>
                </div>
                <a href="{{ route('pagamentos.index') }}" class="text-xs font-bold text-indigo-700 hover:underline">Ver histórico ↗</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($dashboard['recent_movements'] as $movement)
                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2.5 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl text-lg font-semibold
                                {{ $movement['type'] === 'Entrada' ? 'bg-emerald-50 text-emerald-700' : ($movement['type'] === 'Transferência' ? 'bg-indigo-50 text-indigo-700' : 'bg-rose-50 text-rose-600') }}">
                                {{ $movement['type'] === 'Entrada' ? '↙' : ($movement['type'] === 'Transferência' ? '⇄' : '↗') }}
                            </span>
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold text-slate-800">{{ $movement['description'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ \Carbon\CarbonImmutable::parse($movement['date'])->format('d/m') }} · {{ $movement['account'] }} · {{ $movement['type'] }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 text-sm font-bold tabular-nums {{ $movement['type'] === 'Entrada' ? 'text-emerald-700' : 'text-slate-800' }}">
                            {{ $money($movement['amount_cents']) }}
                        </span>
                    </div>
                @empty
                    <div class="finance-empty">Ainda não há movimentações registradas no período.</div>
                @endforelse
            </div>
        </article>
        <div class="space-y-4">
            <article class="finance-panel p-5 sm:p-6">
                <div class="finance-section-head">
                    <div>
                        <h2 class="finance-section-title">Contas mensais a confirmar</h2>
                        <p class="finance-subtitle mt-1">Previsões ainda não são cobranças e não afetam o saldo.</p>
                    </div>
                    <span class="finance-small-tag bg-amber-50 text-amber-700">{{ $dashboard['forecast_count'] }} pendente(s)</span>
                </div>
                <div class="mt-4 rounded-xl bg-amber-50/70 p-4">
                    <p class="text-xs font-semibold text-amber-900">Total previsto (não confirmado)</p>
                    <p class="mt-1 text-2xl font-extrabold tabular-nums text-amber-900">{{ $money($dashboard['forecast_cents']) }}</p>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($dashboard['forecast'] as $bill)
                        <div class="flex min-w-0 items-center justify-between gap-2 py-2 text-sm">
                            <span class="min-w-0 truncate text-slate-700">{{ $bill->description }}</span>
                            <span class="shrink-0 font-bold tabular-nums text-slate-700">{{ $money(\App\Support\FinancialBalance::cents($bill->planned_amount)) }}</span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('contas-fixas.index') }}" class="mt-4 inline-flex text-xs font-bold text-indigo-700 hover:underline">Confirmar valores do mês ↗</a>
            </article>
            <article class="finance-panel p-5 sm:p-6">
                <h2 class="finance-section-title">Acessos rápidos</h2>
                <div class="finance-links mt-4 grid grid-cols-2 gap-2">
                    <a class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-indigo-50 px-2 text-xs font-bold text-indigo-800 hover:bg-indigo-100" href="{{ route('contas.index') }}">▤ Minhas contas</a>
                    <a class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-teal-50 px-2 text-xs font-bold text-teal-800 hover:bg-teal-100" href="{{ route('recebimentos.index') }}">↙ Receber</a>
                    <a class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-amber-50 px-2 text-xs font-bold text-amber-800 hover:bg-amber-100" href="{{ route('contas-fixas.index') }}">▦ Contas mensais</a>
                    <a class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-rose-50 px-2 text-xs font-bold text-rose-800 hover:bg-rose-100" href="{{ route('cartoes.index') }}">▣ Cartões</a>
                </div>
            </article>
        </div>
    </section>

    <p class="pb-2 text-center text-xs text-slate-400">Informações para acompanhamento pessoal · Nenhum valor previsto reduz automaticamente seu saldo.</p>
</div>
@endsection
