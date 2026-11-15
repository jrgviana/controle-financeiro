<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-sky-700">Administração de cartões e faturas</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Cartões de crédito</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">
                Gerencie seus cartões e consulte faturas futuras, vencidas e quitadas. As compras são registradas na tela Gastos.
            </p>
        </div>
        <button type="button" wire:click="openCardForm"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">
            + Cadastrar cartão
        </button>
    </div>

    @if (session()->has('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Meus cartões</h2>
            <p class="text-sm text-slate-500">
                Compromissos em aberto, incluindo parcelas futuras:
                <strong class="font-semibold text-slate-900">R$ {{ \App\Support\FinancialBalance::formatted($totalDebtCents) }}</strong>
            </p>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1.1fr)]">
                <span>Cartão</span><span>Limite</span><span>Em aberto</span><span>Fechamento / vencimento</span><span>Ações</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($summaries as $item)
                    @php $entry = $item['card']; @endphp
                    <div wire:key="card-summary-{{ $entry->id }}"
                         class="grid min-w-0 gap-3 px-4 py-4 transition hover:bg-slate-50/70 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.9fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1.1fr)] lg:items-center {{ $card?->id === $entry->id ? 'bg-sky-50/40' : '' }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <strong class="break-words text-sm text-slate-900">{{ $entry->name }}</strong>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $entry->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $entry->active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $entry->issuer ?: 'Instituição não informada' }}{{ $entry->brand ? ' · '.$entry->brand : '' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 lg:hidden">Limite</p>
                            <p class="mt-1 break-words text-sm font-medium tabular-nums">
                                {{ $entry->credit_limit !== null ? 'R$ '.number_format((float) $entry->credit_limit, 2, ',', '.') : 'Não informado' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 lg:hidden">Em aberto</p>
                            <p class="mt-1 break-words text-sm font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($item['debt_cents']) }}</p>
                        </div>
                        <div class="text-xs">
                            <p><strong>Fecha:</strong> dia {{ $entry->closing_day }} · <strong>Vence:</strong> dia {{ $entry->due_day }}</p>
                            <p class="mt-1 text-slate-500">Melhor compra: {{ $item['best_on']->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex min-w-0 flex-wrap gap-2">
                            <button type="button" wire:click="selectCard({{ $entry->id }})"
                                class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                {{ $card?->id === $entry->id ? 'Selecionado' : 'Ver faturas' }}
                            </button>
                            <button type="button" wire:click="editCard({{ $entry->id }})"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                            <button type="button" wire:click="toggleCard({{ $entry->id }})"
                                wire:confirm="{{ $entry->active ? 'Desativar o cartão para novas compras? Faturas existentes serão preservadas.' : 'Reativar este cartão?' }}"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                {{ $entry->active ? 'Desativar' : 'Reativar' }}
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-600">
                        Cadastre seu primeiro cartão para consultar e administrar suas faturas.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @if ($cardFormOpen)
        <section class="rounded-2xl border border-sky-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ $editingCardId ? 'Editar cartão' : 'Cadastrar cartão' }}</h2>
                <button type="button" wire:click="cancelCardForm"
                    class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">Fechar</button>
            </div>
            <form wire:submit="saveCard" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="card-name" class="mb-1 block text-sm font-medium">Nome do cartão</label>
                    <input id="card-name" wire:model="cardName" type="text" maxlength="100" required placeholder="Ex.: Itaú Mastercard"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('cardName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="card-issuer" class="mb-1 block text-sm font-medium">Banco/instituição (opcional)</label>
                    <input id="card-issuer" wire:model="cardIssuer" type="text" maxlength="100" placeholder="Ex.: Itaú"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('cardIssuer') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="card-brand" class="mb-1 block text-sm font-medium">Bandeira (opcional)</label>
                    <input id="card-brand" wire:model="cardBrand" type="text" maxlength="50" placeholder="Ex.: Visa, Mastercard"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('cardBrand') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="card-limit" class="mb-1 block text-sm font-medium">Limite informado (opcional)</label>
                    <input id="card-limit" wire:model="cardLimit" type="text" data-money-mask inputmode="numeric" autocomplete="off"
                        placeholder="R$ 0,00" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm tabular-nums outline-none focus:border-sky-600">
                    @error('cardLimit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="card-closing" class="mb-1 block text-sm font-medium">Dia do fechamento</label>
                    <input id="card-closing" wire:model="closingDay" type="number" min="1" max="31" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('closingDay') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="card-due" class="mb-1 block text-sm font-medium">Dia do vencimento</label>
                    <input id="card-due" wire:model="dueDay" type="number" min="1" max="31" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('dueDay') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs leading-5 text-slate-500 sm:col-span-2">
                    Compras no dia do fechamento entram no ciclo que está fechando; após essa data, entram no ciclo seguinte.
                    O melhor dia de compra é calculado como o dia posterior ao fechamento. Dias 29–31 são ajustados ao fim do mês quando necessário.
                    Alterar as datas afeta apenas novas compras; parcelas existentes não são reprogramadas.
                </p>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveCard"
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                        {{ $editingCardId ? 'Salvar cartão' : 'Cadastrar cartão' }}
                    </button>
                    <button type="button" wire:click="cancelCardForm"
                        class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium hover:bg-slate-100">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    @if ($card)
        <section>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Faturas · {{ $card->name }}</h2>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                        Cada fatura reúne parcelas com o mesmo vencimento. O saldo anterior em faturas futuras é uma projeção:
                        muda conforme você registra pagamentos das faturas anteriores, sem duplicar a dívida.
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $invoices->count() }} faturas
                </span>
            </div>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.65fr)]">
                    <span>Vencimento</span><span>Situação</span><span>Total da fatura</span><span>Saldo em aberto</span><span>Ação</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($invoices as $row)
                        @php
                            $invoiceItem = $row['invoice'];
                            $selectedHere = $selectedInvoice && $selectedInvoice['invoice']->id === $invoiceItem->id;
                            $isPaid = $row['remaining_cents'] === 0;
                            $isPartial = $row['paid_cents'] > 0 && ! $isPaid;
                        @endphp
                        <div wire:key="invoice-line-{{ $invoiceItem->id }}"
                             class="grid min-w-0 gap-2 px-4 py-4 text-sm lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.65fr)] lg:items-center lg:gap-3 {{ $selectedHere ? 'bg-sky-50/50' : '' }}">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $invoiceItem->due_on->format('d/m/Y') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $invoiceItem->installments->count() }} parcela(s) no mês</p>
                            </div>
                            <div>
                                <span class="text-xs font-semibold {{ $isPaid ? 'text-emerald-700' : ($isPartial ? 'text-amber-700' : 'text-slate-500') }}">
                                    {{ $isPaid ? 'Quitada' : ($isPartial ? 'Parcial' : ($invoiceItem->due_on->isPast() ? 'Vencida' : 'A vencer')) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 lg:hidden">Total da fatura</p>
                                <p class="font-medium tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($row['total_cents']) }}</p>
                                @if ($row['carry_cents'] > 0)
                                    <p class="mt-1 text-xs text-amber-700">Saldo anterior: R$ {{ \App\Support\FinancialBalance::formatted($row['carry_cents']) }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 lg:hidden">Saldo em aberto</p>
                                <p class="font-semibold tabular-nums">R$ {{ \App\Support\FinancialBalance::formatted($row['remaining_cents']) }}</p>
                                @if ($row['paid_cents'] > 0)
                                    <p class="mt-1 text-xs text-slate-500">Pago: R$ {{ \App\Support\FinancialBalance::formatted($row['paid_cents']) }}</p>
                                @endif
                            </div>
                            <div class="flex lg:justify-end">
                                <button type="button" wire:click="selectInvoice({{ $invoiceItem->id }})"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                    {{ $selectedHere ? 'Selecionada' : 'Detalhes' }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500">
                            Nenhuma fatura ainda. Registre compras na tela
                            <a href="{{ route('gastos.index') }}" class="font-semibold text-sky-700 underline">Gastos</a>.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($selectedInvoice)
            @php
                $current = $selectedInvoice['invoice'];
                $open = $selectedInvoice['remaining_cents'];
            @endphp
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-sky-700">Fatura selecionada</p>
                        <h2 class="mt-1 text-xl font-semibold">Vencimento {{ $current->due_on->format('d/m/Y') }}</h2>
                        <p class="mt-2 text-xs text-slate-500">Pagamento parcial não quita uma parcela específica; reduz o total devido na fatura.</p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-xs text-slate-500">Saldo em aberto</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">
                            R$ {{ \App\Support\FinancialBalance::formatted($open) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid gap-2 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-2"><span class="text-slate-500">Parcelas do mês</span><strong>R$ {{ \App\Support\FinancialBalance::formatted($selectedInvoice['installments_cents']) }}</strong></div>
                    <div class="flex justify-between gap-2"><span class="text-slate-500">Saldo anterior projetado</span><strong>R$ {{ \App\Support\FinancialBalance::formatted($selectedInvoice['carry_cents']) }}</strong></div>
                    <div class="flex justify-between gap-2"><span class="text-slate-500">Juros/encargos manuais</span><strong>R$ {{ \App\Support\FinancialBalance::formatted($selectedInvoice['interest_cents']) }}</strong></div>
                    <div class="flex justify-between gap-2"><span class="text-slate-500">Pagamentos registrados</span><strong>R$ {{ \App\Support\FinancialBalance::formatted($selectedInvoice['paid_cents']) }}</strong></div>
                </div>

                <h3 class="mt-6 text-base font-semibold">Compras e parcelas desta fatura</h3>
                <div class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-100">
                    @forelse ($current->installments as $installment)
                        <div wire:key="installment-{{ $installment->id }}" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $installment->purchase?->description ?? 'Compra' }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Parcela {{ $installment->installment_number }} de {{ $installment->purchase?->installments_count ?? '?' }}
                                    · Compra em {{ $installment->purchase?->purchased_on?->format('d/m/Y') ?? '—' }}
                                </p>
                            </div>
                            <span class="font-semibold tabular-nums">R$ {{ number_format((float) $installment->amount, 2, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="px-4 py-4 text-sm text-slate-500">Nenhuma parcela nova nesta fatura. Pode existir saldo transportado de um mês anterior.</p>
                    @endforelse
                    @foreach ($current->charges as $charge)
                        <div wire:key="interest-{{ $charge->id }}" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold text-amber-800">{{ $charge->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">Juros/encargos incluídos manualmente</p>
                            </div>
                            <span class="font-semibold tabular-nums">R$ {{ number_format((float) $charge->amount, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-7 grid gap-5 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <h3 class="font-semibold">Registrar pagamento da fatura</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Permite pagamento parcial ou integral. Apenas o valor pago será descontado da conta bancária.
                        </p>
                        @if ($open > 0 && $accounts->isNotEmpty())
                            <form wire:submit="payInvoice" wire:key="invoice-payment-{{ $current->id }}-{{ $paymentFormVersion }}" class="mt-4 space-y-3">
                                <div>
                                    <label for="card-payment-account" class="mb-1 block text-sm font-medium">Conta pagadora</label>
                                    <select id="card-payment-account" wire:model="paymentAccountId" required
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-sky-600">
                                        <option value="">Selecione uma conta</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('paymentAccountId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="card-payment-amount" class="mb-1 block text-sm font-medium">Valor pago</label>
                                        <input id="card-payment-amount" wire:model="paymentAmount" data-money-mask
                                            type="text" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00" required
                                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm tabular-nums outline-none focus:border-sky-600">
                                        @error('paymentAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="card-payment-date" class="mb-1 block text-sm font-medium">Data do pagamento</label>
                                        <input id="card-payment-date" wire:model="paymentOn" type="date" max="{{ now()->toDateString() }}" required
                                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-600">
                                        @error('paymentOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <button type="submit" wire:loading.attr="disabled" wire:target="payInvoice"
                                    class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                                    Registrar pagamento
                                </button>
                            </form>
                        @elseif ($open === 0)
                            <p class="mt-3 text-sm font-semibold text-emerald-700">Fatura quitada. Nenhum valor pendente neste vencimento.</p>
                        @else
                            <p class="mt-3 text-sm text-amber-700">Cadastre uma conta financeira ativa para registrar pagamentos.</p>
                        @endif
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4 sm:p-5">
                        <h3 class="font-semibold">Adicionar juros/encargos manualmente</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Informe os juros cobrados pelo banco. Após pagamento parcial, selecione a fatura seguinte; o saldo anterior será incluído na projeção.
                            O sistema não inventa taxas.
                        </p>
                        <form wire:submit="addInterest" wire:key="invoice-interest-{{ $current->id }}-{{ $interestFormVersion }}" class="mt-4 space-y-3">
                            <div>
                                <label for="card-interest-description" class="mb-1 block text-sm font-medium">Descrição</label>
                                <input id="card-interest-description" wire:model="interestDescription" type="text" maxlength="160" required
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-600">
                                @error('interestDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="card-interest-amount" class="mb-1 block text-sm font-medium">Valor dos juros</label>
                                <input id="card-interest-amount" wire:model="interestAmount" type="text" data-money-mask inputmode="numeric"
                                    autocomplete="off" required placeholder="R$ 0,00"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm tabular-nums outline-none focus:border-sky-600">
                                @error('interestAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="addInterest"
                                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-100 disabled:opacity-50">
                                Incluir juros na fatura
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-7">
                    <h3 class="font-semibold">Pagamentos já registrados</h3>
                    <div class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-100">
                        @forelse ($current->payments as $payment)
                            <div wire:key="invoice-payment-line-{{ $payment->id }}" class="flex flex-wrap justify-between gap-3 px-4 py-3 text-sm">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $payment->paid_on->format('d/m/Y') }} · {{ $payment->financialAccount?->name ?? 'Conta indisponível' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Pagamento efetivado</p>
                                </div>
                                <span class="font-semibold tabular-nums">R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-4 text-sm text-slate-500">Nenhum pagamento registrado nesta fatura.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

    @endif
</div>
