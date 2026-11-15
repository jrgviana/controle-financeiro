<div>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-sm font-semibold text-sky-700">Etapa 02</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Minhas contas</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Cadastre onde seu dinheiro fica guardado. Os cartões de crédito terão uma área própria.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-sky-100">
            + Nova conta
        </button>
    </div>

    @if (session()->has('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    <section class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Saldo disponível calculado</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $isEstimatedDeficit ? 'Faltam R$' : 'R$' }} {{ $estimatedBalance }}</p>
            <p class="mt-2 text-xs text-slate-500">Saldo inicial + recebimentos + transferências recebidas − gastos pagos − transferências enviadas − faturas pagas. Compras ainda não pagas aparecem em Cartões.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Contas ativas</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $activeCount }}</p>
            <p class="mt-2 text-xs text-slate-500">Contas desativadas continuam registradas.</p>
        </div>
    </section>

    @if ($formOpen)
        <section class="mt-8 rounded-2xl border border-sky-200 bg-white p-6 shadow-sm sm:p-8" aria-label="{{ $editingId ? 'Editar conta' : 'Nova conta' }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">{{ $editingId ? 'Editar conta' : 'Cadastrar nova conta' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">O saldo informado é uma referência inicial, não uma movimentação financeira.</p>
                </div>
                <button type="button" wire:click="cancel" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100">Cancelar</button>
            </div>
            <form wire:submit="save" class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="account-name" class="mb-2 block text-sm font-medium">Nome da conta</label>
                    <input id="account-name" type="text" wire:model="name" maxlength="100" placeholder="Ex.: Nubank, Banco do Brasil, Carteira" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    @error('name') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="account-type" class="mb-2 block text-sm font-medium">Tipo</label>
                    <select id="account-type" wire:model="type" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                        <option value="corrente">Conta corrente</option>
                        <option value="poupanca">Poupança</option>
                        <option value="carteira">Carteira / dinheiro em espécie</option>
                        <option value="outras">Outra conta</option>
                    </select>
                    @error('type') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="account-institution" class="mb-2 block text-sm font-medium">Instituição (opcional)</label>
                    <input id="account-institution" type="text" wire:model="institution" maxlength="100" placeholder="Ex.: Banco do Brasil"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    @error('institution') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="account-balance" class="mb-2 block text-sm font-medium">Saldo inicial</label>
                    <input id="account-balance" type="text" wire:model="initialBalance" data-money-mask inputmode="numeric" autocomplete="off" placeholder="R$ 0,00" required
                        aria-describedby="account-balance-help"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium tabular-nums outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <p id="account-balance-help" class="mt-2 text-xs text-slate-500">Digite apenas os números. Ex.: 123456 aparece como R$ 1.234,56.</p>
                    @error('initialBalance') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                        {{ $editingId ? 'Salvar alterações' : 'Cadastrar conta' }}
                    </button>
                    <button type="button" wire:click="cancel" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium hover:bg-slate-100">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    <section class="mt-9">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Contas cadastradas</h2>
                <a href="{{ route('transferencias.index') }}" class="mt-1 inline-flex text-xs font-semibold text-sky-700 underline underline-offset-2 hover:text-sky-900">Ver histórico de transferências e saques</a>
            </div>
            <span class="text-sm text-slate-500">{{ $accounts->count() }} no total</span>
        </div>


        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 lg:grid lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.95fr)_minmax(0,1.3fr)] lg:gap-3">
                <span>Conta</span>
                <span>Entradas</span>
                <span>Saídas</span>
                <span>Disponível</span>
                <span>Ações</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($accounts as $account)
                    @php
                        $balanceCents = \App\Support\FinancialBalance::loadedCents($account);
                        $incomeCents = \App\Support\FinancialBalance::cents($account->income_received ?? 0);
                        $expenseCents = \App\Support\FinancialBalance::cents($account->expenses_paid ?? 0);
                        $receivedTransferCents = \App\Support\FinancialBalance::cents($account->transfers_received ?? 0);
                        $sentTransferCents = \App\Support\FinancialBalance::cents($account->transfers_sent ?? 0);
                        $cardPaymentCents = \App\Support\FinancialBalance::cents($account->card_payments ?? 0);
                        $typeName = [
                            'corrente' => 'Conta corrente',
                            'poupanca' => 'Poupança',
                            'carteira' => 'Carteira',
                            'outras' => 'Outra conta',
                        ][$account->type] ?? 'Conta';
                    @endphp
                    <div wire:key="account-line-{{ $account->id }}"
                        class="grid min-w-0 gap-x-3 gap-y-3 px-4 py-4 transition hover:bg-slate-50/70 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.95fr)_minmax(0,1.3fr)] lg:items-center {{ $account->active ? '' : 'bg-slate-50/60' }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="break-words text-sm font-semibold text-slate-900">{{ $account->name }}</span>
                                @unless ($account->active)
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Inativa</span>
                                @endunless
                            </div>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $typeName }}{{ $account->institution ? ' · '.$account->institution : '' }}
                            </p>
                            <p class="mt-1 text-xs tabular-nums text-slate-500">
                                Inicial: R$ {{ number_format((float) $account->initial_balance, 2, ',', '.') }}
                            </p>
                        </div>
                        <div class="min-w-0 text-xs text-slate-600">
                            <p class="mb-1 font-semibold text-slate-500 lg:hidden">Entradas</p>
                            <p class="tabular-nums">Receb.: R$ {{ \App\Support\FinancialBalance::formatted($incomeCents) }}</p>
                            <p class="mt-1 tabular-nums">Transf.: R$ {{ \App\Support\FinancialBalance::formatted($receivedTransferCents) }}</p>
                        </div>
                        <div class="min-w-0 text-xs text-slate-600">
                            <p class="mb-1 font-semibold text-slate-500 lg:hidden">Saídas</p>
                            <p class="tabular-nums">Gastos: R$ {{ \App\Support\FinancialBalance::formatted($expenseCents) }}</p>
                            <p class="mt-1 tabular-nums">Transf.: R$ {{ \App\Support\FinancialBalance::formatted($sentTransferCents) }}</p>
                            <p class="mt-1 tabular-nums">Faturas: R$ {{ \App\Support\FinancialBalance::formatted($cardPaymentCents) }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-1 text-xs font-semibold text-slate-500 lg:hidden">Disponível</p>
                            <p class="break-words text-sm font-semibold tabular-nums text-slate-900">
                                {{ $balanceCents < 0 ? 'Faltam R$' : 'R$' }} {{ \App\Support\FinancialBalance::formatted($balanceCents) }}
                            </p>
                        </div>
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            @if ($account->active && $balanceCents > 0)
                                <button type="button" wire:click="openTransfer({{ $account->id }})"
                                    class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                    {{ $account->type === 'carteira' ? 'Transferir' : 'Transferir / Sacar' }}
                                </button>
                            @endif
                            <button type="button" wire:click="edit({{ $account->id }})"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">Editar</button>
                            <button type="button" wire:click="toggleActive({{ $account->id }})"
                                wire:confirm="{{ $account->active ? 'Desativar esta conta? Os dados serão preservados.' : 'Reativar esta conta?' }}"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                {{ $account->active ? 'Desativar' : 'Reativar' }}
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="text-lg font-semibold text-slate-900">Nenhuma conta cadastrada</p>
                        <p class="mt-2 text-sm text-slate-500">Comece cadastrando uma conta bancária ou sua carteira.</p>
                        <button type="button" wire:click="openCreate"
                            class="mt-5 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                            Cadastrar primeira conta
                        </button>
                    </div>
                @endforelse
            </div>
        </div>
        @error('transferSourceId')
            <p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>
        @enderror
    </section>


    <section class="mt-10 border-t border-slate-200 pt-9">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Compromissos e contas a vencer</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Planejamento separado do saldo disponível. Contas de luz, água, internet e faturas de cartão
                    <strong>não reduzem o dinheiro das contas até o pagamento ser confirmado.</strong>
                </p>
            </div>
            <button type="button" wire:click="openBillForm"
                class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                + Nova conta a vencer
            </button>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Contas comuns pendentes</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($billsPendingCents) }}</p>
                <p class="mt-1 text-xs text-slate-500">Somente água, luz, internet e outras contas cadastradas manualmente.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Dívida total nos cartões</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($cardDebtCents) }}</p>
                <p class="mt-1 text-xs text-slate-500">Inclui todas as parcelas futuras e encargos ainda não pagos. Não está somado às contas comuns.</p>
            </div>
        </div>

        <div class="mt-7 flex items-center justify-between gap-2">
            <h3 class="text-base font-semibold">Água, energia, internet e outras contas</h3>
            <span class="text-xs text-slate-500">Valores planejados, sem sinal negativo</span>
        </div>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($bills as $bill)
                    @php $isPaid = $bill->daily_expense_id !== null; @endphp
                    <div wire:key="bill-{{ $bill->id }}" class="grid min-w-0 gap-2 px-4 py-4 text-sm lg:grid-cols-[minmax(0,1.5fr)_110px_125px_100px_minmax(0,1.25fr)] lg:items-center lg:gap-3">
                        <div class="min-w-0">
                            <strong class="break-words text-slate-900">{{ $bill->description }}</strong>
                            @if ($bill->notes)
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $bill->notes }}</p>
                            @endif
                        </div>
                        <p class="text-xs text-slate-600">Vence {{ $bill->due_on->format('d/m/Y') }}</p>
                        <p class="font-semibold tabular-nums">R$ {{ number_format((float) $bill->amount, 2, ',', '.') }}</p>
                        <span class="text-xs font-semibold {{ $isPaid ? 'text-emerald-700' : ($bill->due_on->isPast() ? 'text-amber-700' : 'text-slate-600') }}">
                            {{ $isPaid ? 'Paga' : ($bill->due_on->isPast() ? 'Vencida' : 'A vencer') }}
                        </span>
                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            @if (! $isPaid)
                                <button type="button" wire:click="openBillPayment({{ $bill->id }})"
                                    class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Registrar pagamento</button>
                                <button type="button" wire:click="openBillForm({{ $bill->id }})"
                                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                                <button type="button" wire:click="removeBill({{ $bill->id }})"
                                    wire:confirm="Cancelar esta conta a vencer? Não haverá movimentação no saldo."
                                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Cancelar</button>
                            @else
                                <span class="text-xs text-slate-500">Pago em {{ $bill->payment?->paid_on?->format('d/m/Y') ?? '—' }}
                                    · {{ $bill->payment?->financialAccount?->name ?? 'Conta indisponível' }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Nenhuma conta a vencer cadastrada.</p>
                @endforelse
            </div>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold">Faturas de cartão a vencer</h3>
            <a href="{{ route('cartoes.index') }}" class="text-xs font-semibold text-sky-700 underline">
                Consultar todas as faturas
            </a>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Faturas listadas aqui são compromissos, não gastos já debitados do banco. Não cadastre uma fatura novamente como conta comum.
        </p>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($cardInvoices as $cardRow)
                    @php $invoice = $cardRow['invoice']; @endphp
                    <div wire:key="pending-card-invoice-{{ $invoice->id }}" class="grid min-w-0 gap-2 px-4 py-4 text-sm lg:grid-cols-[minmax(0,1.3fr)_110px_120px_minmax(0,1.4fr)_145px] lg:items-center lg:gap-3">
                        <div>
                            <strong class="break-words text-slate-900">{{ $cardRow['card']->name }}</strong>
                            <p class="mt-1 text-xs text-slate-500">{{ $invoice->installments->count() }} parcela(s) neste mês</p>
                        </div>
                        <p class="text-xs text-slate-600">Vence {{ $invoice->due_on->format('d/m/Y') }}</p>
                        <p class="font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($cardRow['remaining_cents']) }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $cardRow['paid_cents'] > 0 ? 'Pagamento parcial registrado' : ($invoice->due_on->isPast() ? 'Fatura vencida' : 'A vencer') }}
                            @if ($cardRow['carry_cents'] > 0)
                                · Inclui R$ {{ \App\Support\FinancialBalance::formatted($cardRow['carry_cents']) }} de saldo anterior
                            @endif
                        </p>
                        <div class="flex lg:justify-end">
                            <button type="button" wire:click="openCardPayment({{ $invoice->id }})"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Pagar / juros</button>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Nenhuma fatura em aberto.</p>
                @endforelse
            </div>
        </div>
    </section>

    @if ($billFormOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="bill-form-title"
                 class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="bill-form-title" class="text-lg font-semibold">{{ $editingBillId ? 'Editar conta a vencer' : 'Nova conta a vencer' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">O cadastro não desconta dinheiro do saldo bancário.</p>
                    </div>
                    <button type="button" wire:click="closeBillForm" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <form wire:submit="saveBill" class="mt-5 space-y-4">
                    <div>
                        <label for="bill-description" class="mb-1 block text-sm font-medium">Descrição</label>
                        <input id="bill-description" wire:model="billDescription" required maxlength="160"
                            placeholder="Ex.: Conta de energia, internet ou condomínio"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('billDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="bill-value" class="mb-1 block text-sm font-medium">Valor a pagar</label>
                            <input id="bill-value" wire:model="billAmount" data-money-mask inputmode="numeric" autocomplete="off" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm tabular-nums">
                            @error('billAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="bill-date" class="mb-1 block text-sm font-medium">Vencimento</label>
                            <input id="bill-date" type="date" wire:model="billDueOn" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('billDueOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="bill-category" class="mb-1 block text-sm font-medium">Categoria</label>
                        <select id="bill-category" wire:model="billCategory"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="bill-notes" class="mb-1 block text-sm font-medium">Observação (opcional)</label>
                        <input id="bill-notes" wire:model="billNotes" maxlength="500" placeholder="Ex.: competência novembro"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('billNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Salvar compromisso</button>
                        <button type="button" wire:click="closeBillForm" class="rounded-xl border px-5 py-2.5 text-sm">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($billPaymentModalOpen && $billToPay)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="bill-payment-title"
                 class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="bill-payment-title" class="text-lg font-semibold">Confirmar pagamento</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ $billToPay->description }} · R$ {{ number_format((float) $billToPay->amount, 2, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-slate-500">Somente agora esse compromisso será debitado da conta.</p>
                    </div>
                    <button type="button" wire:click="closeBillPayment" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <form wire:submit="confirmBillPayment" class="mt-5 space-y-4">
                    <div>
                        <label for="bill-paying-account" class="mb-1 block text-sm font-medium">Conta pagadora</label>
                        <select id="bill-paying-account" wire:model="billPaymentAccountId" required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Selecione uma conta</option>
                            @foreach ($activeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('billPaymentAccountId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="bill-paid-date" class="mb-1 block text-sm font-medium">Data do pagamento realizado</label>
                        <input id="bill-paid-date" wire:model="billPaidOn" type="date" required max="{{ now()->toDateString() }}"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('billPaidOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmBillPayment"
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">
                        Confirmar pagamento integral
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($cardPaymentModalOpen && $cardInvoiceToPay)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="card-payment-title"
                 class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="card-payment-title" class="text-lg font-semibold">Pagamento de fatura / encargos</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $cardInvoiceToPay['card']->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Vencimento {{ $cardInvoiceToPay['invoice']->due_on->format('d/m/Y') }}
                            · Saldo em aberto R$ {{ \App\Support\FinancialBalance::formatted($cardInvoiceToPay['remaining_cents']) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeCardPayment" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                @if (session()->has('success'))
                    <p class="mt-3 text-xs text-emerald-700">{{ session('success') }}</p>
                @endif
                <form wire:submit="confirmCardPayment" class="mt-5 space-y-3">
                    <h3 class="font-semibold">Registrar pagamento parcial ou integral</h3>
                    <div>
                        <label for="card-paying-account" class="mb-1 block text-sm font-medium">Conta pagadora</label>
                        <select id="card-paying-account" wire:model="cardPaymentAccountId" required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Selecione uma conta</option>
                            @foreach ($activeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('cardPaymentAccountId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="card-payment-value" class="mb-1 block text-sm font-medium">Valor efetivamente pago</label>
                            <input id="card-payment-value" wire:model="cardPaymentAmount" data-money-mask inputmode="numeric"
                                required autocomplete="off" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('cardPaymentAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="card-paid-date" class="mb-1 block text-sm font-medium">Data do pagamento</label>
                            <input id="card-paid-date" wire:model="cardPaymentOn" type="date" max="{{ now()->toDateString() }}" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('cardPaymentOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmCardPayment"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                        Registrar pagamento
                    </button>
                </form>
                <form wire:submit="addCardInterest" class="mt-6 space-y-3 border-t border-slate-200 pt-5">
                    <h3 class="font-semibold">Juros informados manualmente</h3>
                    <p class="text-xs text-slate-500">Inclua os encargos cobrados pelo banco na fatura selecionada; não há cálculo automático de juros.</p>
                    <div>
                        <label for="card-interest-label" class="mb-1 block text-sm font-medium">Descrição do encargo</label>
                        <input id="card-interest-label" wire:model="cardInterestDescription" required maxlength="160"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('cardInterestDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="card-interest-value" class="mb-1 block text-sm font-medium">Valor do encargo</label>
                        <input id="card-interest-value" wire:model="cardInterestAmount" data-money-mask inputmode="numeric" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('cardInterestAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold hover:bg-slate-100">
                        Adicionar juros à fatura
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($transferModalOpen && $modalSource)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6"
             role="presentation" wire:key="transfer-modal-{{ $transferFormVersion }}">
            <div role="dialog" aria-modal="true" aria-labelledby="transfer-modal-title"
                 class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="transfer-modal-title" class="text-lg font-semibold text-slate-900">Movimentar saldo</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Origem: <strong class="font-semibold text-slate-700">{{ $modalSource->name }}</strong>
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">
                            Disponível: R$ {{ \App\Support\FinancialBalance::formatted(max($modalSourceBalance, 0)) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeTransfer" aria-label="Fechar"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">
                        Fechar
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-5 sm:px-6">
                    <form wire:submit="saveTransfer" class="space-y-4">
                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold text-slate-900">O que deseja fazer?</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                                    <input type="radio" wire:model.change.live="transferMode" value="transfer" class="accent-slate-900">
                                    Transferir para outra conta
                                </label>
                                @if ($modalSource->type !== 'carteira')
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                                        <input type="radio" wire:model.change.live="transferMode" value="withdrawal" class="accent-slate-900">
                                        Saque em dinheiro
                                    </label>
                                @endif
                            </div>
                            @error('transferMode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </fieldset>
                        <div>
                            <label for="quick-target" class="mb-2 block text-sm font-medium text-slate-900">
                                {{ $transferMode === 'withdrawal' ? 'Carteira que receberá o dinheiro' : 'Conta de destino' }}
                            </label>
                            <select id="quick-target" wire:model="transferTargetId" required
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-700">
                                <option value="">Selecione o destino</option>
                                @foreach ($transferDestinations as $destination)
                                    <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                                @endforeach
                            </select>
                            @error('transferTargetId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @if ($transferMode === 'withdrawal')
                                <p class="mt-2 text-xs text-slate-500">O saque será registrado como transferência para a Carteira, com a identificação “Saque em espécie”.</p>
                            @endif
                            @if ($transferDestinations->isEmpty())
                                <p class="mt-2 text-xs text-amber-700">
                                    {{ $transferMode === 'withdrawal' ? 'Cadastre uma conta do tipo Carteira para registrar saques.' : 'Cadastre ou reative outra conta para transferir.' }}
                                </p>
                            @endif
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="quick-amount" class="mb-2 block text-sm font-medium text-slate-900">Valor</label>
                                <input id="quick-amount" type="text" data-money-mask wire:model="transferAmount"
                                    inputmode="numeric" autocomplete="off" required placeholder="R$ 0,00"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold tabular-nums outline-none focus:border-sky-700">
                                @error('transferAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="quick-date" class="mb-2 block text-sm font-medium text-slate-900">Data</label>
                                <input id="quick-date" type="date" wire:model="transferDate" max="{{ now()->toDateString() }}" required
                                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-700">
                                @error('transferDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label for="quick-note" class="mb-2 block text-sm font-medium text-slate-900">Observação (opcional)</label>
                            <input id="quick-note" type="text" wire:model="transferNotes" maxlength="450"
                                placeholder="{{ $transferMode === 'withdrawal' ? 'Ex.: saque para gastos em dinheiro' : 'Ex.: sobra do salário' }}"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-700">
                            @error('transferNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                            <button type="button" wire:click="closeTransfer"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium hover:bg-slate-100">Cancelar</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveTransfer"
                                @disabled($transferDestinations->isEmpty())
                                class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">
                                {{ $transferMode === 'withdrawal' ? 'Confirmar saque' : 'Confirmar transferência' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
