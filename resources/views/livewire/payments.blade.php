<div class="space-y-7">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-sky-700">Controle de pagamentos</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Pagamentos</h1>
            <p class="mt-2 text-sm text-slate-500">Confira o dinheiro disponível e quite seus compromissos.</p>
        </div>
        <div>
            <label for="payments-month" class="mb-1 block text-xs font-semibold text-slate-500">Mês de referência</label>
            <input id="payments-month" type="month" wire:model.change.live="selectedMonth"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
        </div>
    </div>

    @if (session()->has('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Recebimentos do mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($agenda['income_cents']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Gastos já pagos no mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($agenda['paid_cents']) }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Disponível para pagar agora</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($agenda['available_cents']) }}</p>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold">Saldo por conta</h2>
            <p class="text-xs text-slate-500">Pagamentos usam o saldo da conta escolhida.</p>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($agenda['accounts'] as $account)
                <div class="flex justify-between gap-3 rounded-xl bg-slate-50 px-3 py-3 text-sm" wire:key="payment-account-{{ $account['id'] }}">
                    <div class="min-w-0">
                        <p class="break-words font-medium">{{ $account['name'] }}</p>
                        @unless ($account['active'])
                            <p class="text-xs text-slate-500">Inativa · não disponível para pagamentos</p>
                        @endunless
                    </div>
                    <strong class="shrink-0 tabular-nums">
                        {{ $account['balance_cents'] < 0 ? 'Saldo a regularizar' : 'R$ '.\App\Support\FinancialBalance::formatted($account['balance_cents']) }}
                    </strong>
                </div>
            @empty
                <p class="text-sm text-slate-500">Cadastre uma conta financeira para começar.</p>
            @endforelse
        </div>
    </section>

    <section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Compromissos para pagar</h2>
            <div class="text-right">
                <p class="text-sm font-semibold tabular-nums">
                    Para pagar neste mês: R$ {{ \App\Support\FinancialBalance::formatted($agenda['pending_cents']) }}
                </p>
                @if ($agenda['overdue_cents'] > 0)
                    <p class="text-xs font-medium text-amber-700">
                        Vencidos: R$ {{ \App\Support\FinancialBalance::formatted($agenda['overdue_cents']) }}
                    </p>
                @endif
            </div>
        </div>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($agenda['pending'] as $entry)
                    <div wire:key="due-{{ $entry['key'] }}"
                        class="grid min-w-0 gap-2 px-4 py-4 text-sm md:grid-cols-[minmax(0,1.8fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_auto] md:items-center md:gap-4">
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-slate-900">{{ $entry['description'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $entry['detail'] }}</p>
                            @if (($entry['paid_cents'] ?? 0) > 0)
                                <p class="mt-1 text-xs text-slate-500">
                                    Já pago: R$ {{ \App\Support\FinancialBalance::formatted($entry['paid_cents']) }}
                                </p>
                            @endif
                        </div>
                        <div class="text-xs">
                            <p class="text-slate-500">Vence {{ $entry['due_on']->format('d/m/Y') }}</p>
                            <p class="mt-1 font-semibold {{ $entry['status'] === 'Vencida' ? 'text-amber-700' : 'text-slate-600' }}">
                                {{ $entry['status'] }}
                            </p>
                        </div>
                        <strong class="tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($entry['amount_cents']) }}</strong>
                        <div class="flex flex-wrap items-center gap-2 md:justify-end">
                            <button type="button" wire:click="openPayment('{{ $entry['key'] }}')"
                                class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Pagar
                            </button>
                            <button type="button" wire:click="openPostpone('{{ $entry['key'] }}')"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                Não pagar neste mês
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-9 text-center text-sm text-slate-500">Nenhum compromisso pendente até o mês selecionado.</p>
                @endforelse
            </div>
        </div>
        @if ($agenda['postponed_this_month']->isNotEmpty())
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold">Não pagos neste mês — transferidos para o próximo</h3>
                @error('postponeReason')
                    <p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-2 divide-y divide-slate-100">
                    @foreach ($agenda['postponed_this_month'] as $event)
                        <div wire:key="postponement-{{ $event->id }}"
                            class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ $event->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $event->source === 'partial' ? 'Pagamento parcial' : 'Não pago' }}
                                    · Vai para {{ $event->to_month->format('m/Y') }}
                                    @if ($event->reason)
                                        · {{ $event->reason }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <strong class="tabular-nums">
                                    R$ {{ \App\Support\FinancialBalance::formatted(\App\Support\FinancialBalance::cents($event->remaining_amount)) }}
                                </strong>
                                @if ($event->source === 'manual')
                                    <button type="button" wire:click="undoPostpone({{ $event->id }})"
                                        class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium hover:bg-slate-100">
                                        Desfazer
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($agenda['next_card_due'] !== null)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                <span>Próximo vencimento de cartão: {{ $agenda['next_card_due']->format('d/m/Y') }}</span>
                <button type="button" wire:click="goToMonth('{{ $agenda['next_card_due']->format('Y-m') }}')"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                    Ver mês
                </button>
            </div>
        @endif
    </section>

    <section>
        <h2 class="text-lg font-semibold">Movimentações confirmadas no mês</h2>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($agenda['movements'] as $movement)
                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm" wire:key="movement-{{ $loop->index }}">
                        <div class="min-w-0">
                            <p class="break-words font-medium">{{ $movement['description'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ \Carbon\CarbonImmutable::parse($movement['date'])->format('d/m/Y') }}
                                · {{ $movement['account'] }}
                                · {{ $movement['type'] }}
                            </p>
                        </div>
                        <strong class="shrink-0 tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($movement['amount_cents']) }}</strong>
                    </div>
                @empty
                    <p class="px-5 py-7 text-center text-sm text-slate-500">Nenhuma movimentação confirmada neste mês.</p>
                @endforelse
            </div>
        </div>
    </section>

    @if ($paymentOpen && $selectedRow)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="pay-dialog-title"
                class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="pay-dialog-title" class="text-lg font-semibold">Registrar pagamento</h2>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $selectedRow['description'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Vencimento {{ $selectedRow['due_on']->format('d/m/Y') }}</p>
                    </div>
                    <button type="button" wire:click="closePayment"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium">Fechar</button>
                </div>
                <form wire:submit="confirmPayment" class="mt-5 space-y-4">
                    <div>
                        <label for="payment-account" class="mb-1 block text-sm font-semibold">Conta pagadora</label>
                        <select id="payment-account" wire:model="paymentAccountId" required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm">
                            <option value="">Selecione a conta</option>
                            @foreach ($agenda['accounts']->where('active', true) as $account)
                                <option value="{{ $account['id'] }}">
                                    {{ $account['name'] }} · R$ {{ \App\Support\FinancialBalance::formatted($account['usable_cents']) }}
                                </option>
                            @endforeach
                        </select>
                        @error('paymentAccountId') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if ($selectedRow['kind'] === 'recurring')
                        <div>
                            <label for="payment-total" class="mb-1 block text-sm font-semibold">Valor da conta neste mês</label>
                            <input id="payment-total" wire:model="billTotalAmount" data-money-mask inputmode="numeric"
                                autocomplete="off" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm tabular-nums">
                            @error('billTotalAmount') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="payment-amount" class="mb-1 block text-sm font-semibold">Valor pago</label>
                            <input id="payment-amount" wire:model="paymentAmount" data-money-mask inputmode="numeric"
                                autocomplete="off" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm tabular-nums">
                            @error('paymentAmount') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="payment-paid-on" class="mb-1 block text-sm font-semibold">Data do pagamento</label>
                            <input id="payment-paid-on" wire:model="paidOn" type="date" max="{{ now()->toDateString() }}" required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm">
                            @error('paidOn') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Se pagar apenas uma parte, o restante será transferido para o mês seguinte.</p>
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmPayment"
                        class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">
                        Confirmar pagamento
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($postponeOpen && $postponeRow)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="postpone-dialog-title"
                class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                <h2 id="postpone-dialog-title" class="text-lg font-semibold">Não pagar neste mês?</h2>
                <p class="mt-3 text-sm text-slate-700">{{ $postponeRow['description'] }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">
                    R$ {{ \App\Support\FinancialBalance::formatted($postponeRow['amount_cents']) }}
                </p>
                <p class="mt-2 text-sm text-slate-600">
                    A pendência será exibida no mês seguinte como dívida em aberto,
                    mantendo o vencimento original. Nenhum dinheiro sairá da conta.
                </p>
                <form wire:submit="confirmPostpone" class="mt-4 space-y-4">
                    <div>
                        <label for="postpone-reason" class="mb-1 block text-sm font-semibold">Observação (opcional)</label>
                        <textarea id="postpone-reason" wire:model="postponeReason" rows="2"
                            placeholder="Ex.: Vou pagar quando receber"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('postponeReason') <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" wire:click="closePostpone"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium">Cancelar</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="confirmPostpone"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            Confirmar não pagamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
