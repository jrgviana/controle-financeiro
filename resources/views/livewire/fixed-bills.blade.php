<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-sky-700">Planejamento e vencimentos</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Contas fixas</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">
                Acompanhe suas contas e pagamentos previstos.
            </p>
        </div>
        <button type="button" wire:click="openFixedForm"
            class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">
            + Cadastrar conta mensal
        </button>
    </div>

    @if (session()->has('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Planejamento mensal</h2>
            <p class="mt-1 text-sm text-slate-500">Valores previstos para o mês selecionado.</p>
        </div>
        <div>
            <label for="fixed-month" class="mb-1 block text-xs font-semibold text-slate-500">Competência</label>
            <input id="fixed-month" wire:model.change.live="selectedMonth" type="month"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Contas do mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($plannedCents) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Pagamentos nos cartões</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($cardOverviewCents) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-600">Total do mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($totalOverviewCents) }}</p>
        </div>
    </div>

    <section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Pagamentos nos cartões</h2>
        </div>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($cardPaymentSummaries as $summary)
                    <div wire:key="monthly-card-{{ $summary['card']->id }}"
                         class="flex min-w-0 flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-semibold text-slate-900">{{ $summary['card']->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $summary['groups']->count() }} vencimento(s)
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <strong class="text-base tabular-nums text-slate-900">
                                R$ {{ \App\Support\FinancialBalance::formatted($summary['amount_cents']) }}
                            </strong>
                            <button type="button" wire:click="openCardBreakdown({{ $summary['card']->id }})"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                Ver cobranças
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Nenhum pagamento de cartão previsto neste mês.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Contas mensais</h2>
            
        </div>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden grid-cols-12 gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid">
                <span class="col-span-4">Compromisso</span>
                <span class="col-span-2">Vencimento</span>
                <span class="col-span-2">Valor no mês</span>
                <span class="col-span-1">Situação</span>
                <span class="col-span-3 text-right">Ações</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($fixedRows as $row)
                    @php $fixed = $row['fixed']; $bill = $row['bill']; @endphp
                    <div wire:key="fixed-row-{{ $fixed->id }}"
                         class="grid min-w-0 gap-2 px-4 py-4 text-sm lg:grid-cols-12 lg:items-center lg:gap-3">
                        <div class="min-w-0 lg:col-span-4">
                            <p class="break-words font-semibold text-slate-900">{{ $fixed->description }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $categories[$fixed->category] ?? 'Outros' }} · Vence normalmente no dia {{ $fixed->due_day }}
                                @unless ($fixed->active) · Pausada @endunless
                            </p>
                        </div>
                        <div class="lg:col-span-2">
                            <span class="text-xs text-slate-500 lg:hidden">Vencimento: </span>
                            <span class="font-medium">{{ $row['due_on']->format('d/m/Y') }}</span>
                        </div>
                        <div class="font-semibold tabular-nums lg:col-span-2">
                            R$ {{ \App\Support\FinancialBalance::formatted($row['amount_cents']) }}
                            @unless ($bill)
                                <p class="mt-1 text-xs font-normal text-slate-500">Valor previsto</p>
                            @endunless
                        </div>
                        <div class="lg:col-span-1">
                            <span class="text-xs font-semibold {{ $row['paid'] ? 'text-emerald-700' : ($row['due_on']->isPast() ? 'text-amber-700' : 'text-slate-600') }}">
                                {{ $row['paid'] ? 'Paga' : ($bill ? ($row['due_on']->isPast() ? 'Vencida' : 'A pagar') : 'Prevista') }}
                            </span>
                        </div>
                        <div class="flex min-w-0 flex-wrap items-center gap-2 lg:col-span-3 lg:justify-end">
                            @if ($row['paid'])
                                <span class="text-xs text-slate-500">Quitada em {{ $bill?->payment?->paid_on?->format('d/m/Y') ?? '—' }}</span>
                            @else
                                @if ($fixed->active || $bill)
                                    <button type="button" wire:click="openOccurrence({{ $fixed->id }})"
                                        class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                        {{ $bill ? 'Alterar valor' : 'Informar valor' }}
                                    </button>
                                @endif
                                @if ($bill)
                                    <button type="button" wire:click="openPayment({{ $bill->id }})"
                                        class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                        Pagar
                                    </button>
                                @endif
                            @endif
                            <button type="button" wire:click="openFixedForm({{ $fixed->id }})"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                            <button type="button" wire:click="toggleFixed({{ $fixed->id }})"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                {{ $fixed->active ? 'Pausar' : 'Reativar' }}
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-9 text-center text-sm text-slate-500">
                        Nenhuma conta fixa neste mês. Cadastre uma conta recorrente para exibir automaticamente os próximos vencimentos.
                    </p>
                @endforelse
            </div>
        </div>

        @error('occurrenceAmount') <p class="mt-2 text-sm text-amber-700">{{ $message }}</p> @enderror
    </section>

    @if ($oneOff->isNotEmpty())
    <section>
        <h2 class="text-lg font-semibold">Outras contas registradas</h2>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @forelse ($oneOff as $bill)
                    <div wire:key="oneoff-row-{{ $bill->id }}" class="grid min-w-0 gap-2 px-4 py-4 text-sm lg:grid-cols-12 lg:items-center">
                        <div class="lg:col-span-4">
                            <p class="font-semibold text-slate-900">{{ $bill->description }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $bill->notes }}</p>
                        </div>
                        <p class="text-xs text-slate-600 lg:col-span-2">Vence {{ $bill->due_on->format('d/m/Y') }}</p>
                        <p class="font-semibold tabular-nums lg:col-span-2">R$ {{ number_format((float) $bill->amount, 2, ',', '.') }}</p>
                        <p class="text-xs font-semibold {{ $bill->daily_expense_id !== null ? 'text-emerald-700' : 'text-slate-500' }} lg:col-span-1">
                            {{ $bill->daily_expense_id !== null ? 'Paga' : 'A pagar' }}
                        </p>
                        <div class="flex flex-wrap gap-2 lg:col-span-3 lg:justify-end">
                            @if ($bill->daily_expense_id === null)
                                <button type="button" wire:click="openPayment({{ $bill->id }})"
                                    class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Pagar</button>
                                <button type="button" wire:click="openOneOff({{ $bill->id }})"
                                    class="rounded-lg border px-3 py-2 text-xs font-semibold">Editar</button>
                                <button type="button" wire:click="removeOneOff({{ $bill->id }})"
                                    wire:confirm="Cancelar esta cobrança avulsa? Não altera o saldo."
                                    class="rounded-lg border px-3 py-2 text-xs font-semibold">Cancelar</button>
                            @else
                                <span class="text-xs text-slate-500">Quitada em {{ $bill->payment?->paid_on?->format('d/m/Y') ?? '—' }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-7 text-center text-sm text-slate-500">Nenhuma conta avulsa neste mês.</p>
                @endforelse
            </div>
        </div>
    </section>
    @endif

    @if ($cardBreakdownOpen && $selectedCardBreakdown)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="card-breakdown-title"
                class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 id="card-breakdown-title" class="text-xl font-semibold text-slate-900">
                            {{ $selectedCardBreakdown['card']->name }}
                        </h2>
                        <p class="mt-1 text-xl font-semibold tabular-nums">
                            R$ {{ \App\Support\FinancialBalance::formatted($selectedCardBreakdown['amount_cents']) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeCardBreakdown"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-100">
                        Fechar
                    </button>
                </div>
                <div class="min-h-0 space-y-4 overflow-y-auto pt-4">
                    @foreach ($selectedCardBreakdown['groups'] as $group)
                        <div wire:key="card-breakdown-due-{{ $group['invoice']->id }}" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">Vencimento {{ $group['due_on']->format('d/m/Y') }}</p>
                                    @if ($group['paid_cents'] > 0)
                                        <p class="mt-1 text-xs text-slate-500">
                                            Pago: R$ {{ \App\Support\FinancialBalance::formatted($group['paid_cents']) }}
                                            · Em aberto: R$ {{ \App\Support\FinancialBalance::formatted($group['remaining_cents']) }}
                                        </p>
                                    @endif
                                </div>
                                <strong class="text-base tabular-nums">
                                    R$ {{ \App\Support\FinancialBalance::formatted($group['amount_cents']) }}
                                </strong>
                            </div>
                            <div class="mt-3 divide-y divide-slate-100">
                                @foreach ($group['items'] as $item)
                                    <div class="flex items-center justify-between gap-3 py-2 text-sm">
                                        <div class="min-w-0">
                                            <p class="break-words font-medium text-slate-800">{{ $item['description'] }}</p>
                                            @if ($item['installment_label'] !== '')
                                                <p class="text-xs text-slate-500">
                                                    Parcela {{ $item['installment_label'] }}
                                                    @if ($item['advanced']) · Antecipada @endif
                                                </p>
                                            @endif
                                        </div>
                                        <strong class="shrink-0 tabular-nums">
                                            R$ {{ \App\Support\FinancialBalance::formatted($item['amount_cents']) }}
                                        </strong>
                                    </div>
                                @endforeach
                            </div>
                            @if ($group['is_current_due'] && $group['remaining_cents'] > 0)
                                <div class="mt-3 flex justify-end">
                                    <button type="button" wire:click="openCardPayment({{ $group['invoice']->id }})"
                                        class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                        Registrar pagamento
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($fixedFormOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="fixed-title" class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="fixed-title" class="text-lg font-semibold">{{ $editingFixedId ? 'Editar conta mensal' : 'Cadastrar conta mensal' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Vencimento mensal; o valor pode mudar a cada mês.</p>
                    </div>
                    <button type="button" wire:click="closeFixedForm" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <form wire:submit="saveFixed" class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="fixed-description" class="mb-1 block text-sm font-medium">Descrição</label>
                        <input id="fixed-description" wire:model="description" required maxlength="160" placeholder="Ex.: Energia elétrica"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fixed-amount" class="mb-1 block text-sm font-medium">Valor de referência</label>
                        <input id="fixed-amount" wire:model="plannedAmount" data-money-mask inputmode="numeric" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('plannedAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fixed-day" class="mb-1 block text-sm font-medium">Dia de vencimento</label>
                        <input id="fixed-day" wire:model="dueDay" type="number" min="1" max="31" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('dueDay') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fixed-start" class="mb-1 block text-sm font-medium">Primeiro mês</label>
                        <input id="fixed-start" wire:model="startsMonth" type="month" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('startsMonth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fixed-category" class="mb-1 block text-sm font-medium">Categoria</label>
                        <select id="fixed-category" wire:model="category"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="fixed-notes" class="mb-1 block text-sm font-medium">Observação (opcional)</label>
                        <input id="fixed-notes" wire:model="notes" maxlength="500" placeholder="Ex.: valor estimado, contrato..."
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Salvar conta mensal</button>
                        <button type="button" wire:click="closeFixedForm" class="rounded-xl border px-5 py-3 text-sm">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($occurrenceFormOpen && $occurrenceRecurringId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="occurrence-title" class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="occurrence-title" class="text-lg font-semibold">Informar valor do mês</h2>
                        <p class="mt-1 text-xs text-slate-500">Competência {{ $selectedMonth }}.</p>
                    </div>
                    <button type="button" wire:click="closeOccurrence" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <form wire:submit="saveOccurrence" class="mt-5 space-y-4">
                    <div>
                        <label for="occurrence-amount" class="mb-1 block text-sm font-medium">Valor da cobrança neste mês</label>
                        <input id="occurrence-amount" wire:model="occurrenceAmount" data-money-mask inputmode="numeric" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('occurrenceAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="occurrence-due" class="mb-1 block text-sm font-medium">Vencimento efetivo neste mês</label>
                        <input id="occurrence-due" wire:model="occurrenceDueOn" type="date" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('occurrenceDueOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-slate-500">Salvar esta cobrança não significa que ela foi paga.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Confirmar cobrança</button>
                        <button type="button" wire:click="closeOccurrence" class="rounded-xl border px-5 py-2.5 text-sm">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($oneOffFormOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="oneoff-title" class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="oneoff-title" class="text-lg font-semibold">{{ $editingOneOffId ? 'Editar conta avulsa' : 'Nova conta avulsa' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Cobrança única, sem recorrência nos meses seguintes.</p>
                    </div>
                    <button type="button" wire:click="closeOneOff" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <form wire:submit="saveOneOff" class="mt-5 space-y-4">
                    <div>
                        <label for="oneoff-description" class="mb-1 block text-sm font-medium">Descrição</label>
                        <input id="oneoff-description" wire:model="oneOffDescription" maxlength="160" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('oneOffDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="oneoff-amount" class="mb-1 block text-sm font-medium">Valor</label>
                            <input id="oneoff-amount" wire:model="oneOffAmount" data-money-mask inputmode="numeric" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('oneOffAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="oneoff-date" class="mb-1 block text-sm font-medium">Data de vencimento</label>
                            <input id="oneoff-date" wire:model="oneOffDueOn" type="date" required
                                class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('oneOffDueOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="oneoff-category" class="mb-1 block text-sm font-medium">Categoria</label>
                        <select id="oneoff-category" wire:model="oneOffCategory" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="oneoff-notes" class="mb-1 block text-sm font-medium">Observação (opcional)</label>
                        <input id="oneoff-notes" wire:model="oneOffNotes" maxlength="500"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('oneOffNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Salvar conta avulsa</button>
                        <button type="button" wire:click="closeOneOff" class="rounded-xl border px-5 py-2.5 text-sm">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($paymentFormOpen && $billToPay)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="fixed-payment-title" class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="fixed-payment-title" class="text-lg font-semibold">Registrar pagamento</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $billToPay->description }} · R$ {{ number_format((float) $billToPay->amount, 2, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-slate-500">Vence em {{ $billToPay->due_on->format('d/m/Y') }}</p>
                    </div>
                    <button type="button" wire:click="closePayment" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <form wire:submit="confirmPayment" class="mt-5 space-y-4">
                    <div>
                        <label for="fixed-paying-account" class="mb-1 block text-sm font-medium">Conta de onde saiu o dinheiro</label>
                        <select id="fixed-paying-account" wire:model="billPaymentAccountId" required
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Selecione a conta pagadora</option>
                            @foreach ($activeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('billPaymentAccountId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fixed-paid-date" class="mb-1 block text-sm font-medium">Data do pagamento efetivo</label>
                        <input id="fixed-paid-date" wire:model="billPaidOn" type="date" max="{{ now()->toDateString() }}" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @error('billPaidOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-slate-500">Este pagamento será registrado uma única vez em Gastos e descontado do saldo da conta.</p>
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmPayment"
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">
                        Confirmar pagamento integral
                    </button>
                </form>
            </div>
        </div>
    @endif
    @if ($cardPaymentOpen && $cardInvoiceToPay)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-6">
            <div role="dialog" aria-modal="true" aria-labelledby="card-month-payment-title"
                 class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl sm:p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="card-month-payment-title" class="text-lg font-semibold">Pagar fatura do cartão</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $cardInvoiceToPay['card']->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Vencimento {{ $cardInvoiceToPay['invoice']->due_on->format('d/m/Y') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeCardPayment" class="rounded-lg border px-3 py-2 text-sm">Fechar</button>
                </div>
                <div class="mt-4 rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Saldo em aberto da fatura (parcelas normais + antecipadas)</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        R$ {{ \App\Support\FinancialBalance::formatted($cardInvoiceToPay['row']['remaining_cents']) }}
                    </p>
                </div>
                <form wire:submit="confirmCardPayment" class="mt-5 space-y-4">
                    <div>
                        <label for="monthly-card-account" class="mb-1 block text-sm font-medium">Conta de onde sairá o pagamento</label>
                        <select id="monthly-card-account" wire:model="cardPayAccountId" required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Selecione uma conta</option>
                            @foreach ($activeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('cardPayAccountId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="monthly-card-amount" class="mb-1 block text-sm font-medium">Valor efetivamente pago</label>
                            <input id="monthly-card-amount" wire:model="cardPayAmount" data-money-mask inputmode="numeric"
                                   autocomplete="off" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('cardPayAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="monthly-card-paid-date" class="mb-1 block text-sm font-medium">Data do pagamento</label>
                            <input id="monthly-card-paid-date" wire:model="cardPayDate" type="date" max="{{ now()->toDateString() }}"
                                   required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @error('cardPayDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs leading-5 text-slate-500">
                        Você pode pagar a fatura inteira ou parte dela. Somente o valor confirmado será descontado da conta.
                        Para adicionar juros manualmente, abra os detalhes em Cartões.
                    </p>
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmCardPayment"
                            class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">
                        Confirmar pagamento da fatura
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
