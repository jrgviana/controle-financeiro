<div class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-sky-700">Etapa 06 · Compras e faturas</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Cartões de crédito</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">
                Cada compra gera parcelas nas faturas correspondentes. O dinheiro só sai da conta bancária ao registrar o pagamento da fatura.
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
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-lg font-semibold">Meus cartões</h2>
            <div class="text-sm text-slate-500">
                Total em aberto, incluindo parcelas futuras: <strong class="font-semibold text-slate-900">R$ {{ \App\Support\FinancialBalance::formatted($totalDebtCents) }}</strong>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @forelse ($summaries as $item)
                @php $entry = $item['card']; @endphp
                <div wire:key="card-summary-{{ $entry->id }}"
                     class="min-w-0 rounded-2xl border bg-white p-5 shadow-sm {{ $card?->id === $entry->id ? 'border-sky-300 ring-1 ring-sky-100' : 'border-slate-200' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-base font-semibold text-slate-900">{{ $entry->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $entry->issuer ?: 'Instituição não informada' }}{{ $entry->brand ? ' · '.$entry->brand : '' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $entry->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $entry->active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-slate-500">Compromissos em aberto</p>
                            <p class="mt-1 break-words text-lg font-semibold tabular-nums">
                                R$ {{ \App\Support\FinancialBalance::formatted($item['debt_cents']) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Limite informado</p>
                            <p class="mt-1 font-semibold tabular-nums">
                                {{ $entry->credit_limit !== null ? 'R$ '.number_format((float) $entry->credit_limit, 2, ',', '.') : 'Não informado' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Fecha / vence</p>
                            <p class="mt-1 font-medium">Dia {{ $entry->closing_day }} / dia {{ $entry->due_day }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Próximo melhor dia</p>
                            <p class="mt-1 font-medium">{{ $item['best_on']->format('d/m/Y') }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="selectCard({{ $entry->id }})"
                            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            {{ $card?->id === $entry->id ? 'Cartão selecionado' : 'Ver compras e faturas' }}
                        </button>
                        <button type="button" wire:click="editCard({{ $entry->id }})"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                        <button type="button" wire:click="toggleCard({{ $entry->id }})"
                            wire:confirm="{{ $entry->active ? 'Desativar o cartão para novas compras? As parcelas existentes serão preservadas.' : 'Reativar este cartão?' }}"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                            {{ $entry->active ? 'Desativar' : 'Reativar' }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-600 md:col-span-2">
                    Cadastre seu primeiro cartão para começar a lançar compras e acompanhar faturas.
                </div>
            @endforelse
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
        <section class="rounded-2xl border border-sky-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Lançar compra · {{ $card->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        À vista = 1/1. Parcelada = 1 de N, 2 de N… O lançamento não debita a conta bancária.
                    </p>
                </div>
                @unless ($card->active)
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Inativo para novas compras</span>
                @endunless
            </div>
            <form wire:submit="savePurchase" wire:key="card-purchase-{{ $card->id }}-{{ $purchaseFormVersion }}"
                  class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="purchase-description" class="mb-1 block text-sm font-medium">Descrição da compra</label>
                    <input id="purchase-description" type="text" wire:model="purchaseDescription" maxlength="160" required
                        placeholder="Ex.: Celular, supermercado, combustível"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('purchaseDescription') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="purchase-value" class="mb-1 block text-sm font-medium">Valor total da compra</label>
                    <input id="purchase-value" type="text" wire:model="purchaseAmount" data-money-mask inputmode="numeric" autocomplete="off"
                        placeholder="R$ 0,00" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-medium tabular-nums outline-none focus:border-sky-600">
                    @error('purchaseAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="purchase-date" class="mb-1 block text-sm font-medium">Data da compra</label>
                    <input id="purchase-date" type="date" wire:model="purchaseOn" max="{{ now()->toDateString() }}" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('purchaseOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="purchase-installments" class="mb-1 block text-sm font-medium">Número de parcelas</label>
                    <input id="purchase-installments" type="number" wire:model="installmentsCount" min="1" max="60" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    <p class="mt-1 text-xs text-slate-500">Use 1 para compras à vista.</p>
                    @error('installmentsCount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="purchase-category" class="mb-1 block text-sm font-medium">Categoria</label>
                    <select id="purchase-category" wire:model="purchaseCategory" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-600">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('purchaseCategory') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="purchase-note" class="mb-1 block text-sm font-medium">Observação (opcional)</label>
                    <input id="purchase-note" type="text" wire:model="purchaseNotes" maxlength="500" placeholder="Detalhes para lembrar"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-sky-600">
                    @error('purchaseNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="savePurchase" @disabled(! $card->active)
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                        Registrar compra
                    </button>
                    <button type="button" wire:click="clearPurchase"
                        class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium hover:bg-slate-100">Limpar campos</button>
                </div>
            </form>
        </section>

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
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($invoices as $row)
                    @php
                        $invoiceItem = $row['invoice'];
                        $selectedHere = $selectedInvoice && $selectedInvoice['invoice']->id === $invoiceItem->id;
                        $isPaid = $row['remaining_cents'] === 0;
                        $isPartial = $row['paid_cents'] > 0 && ! $isPaid;
                    @endphp
                    <button type="button" wire:key="invoice-tile-{{ $invoiceItem->id }}"
                            wire:click="selectInvoice({{ $invoiceItem->id }})"
                            class="min-w-0 rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:border-sky-300
                            {{ $selectedHere ? 'border-sky-300 ring-1 ring-sky-100' : 'border-slate-200' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ $invoiceItem->due_on->format('m/Y') }}</span>
                            <span class="text-xs font-semibold {{ $isPaid ? 'text-emerald-700' : ($isPartial ? 'text-amber-700' : 'text-slate-500') }}">
                                {{ $isPaid ? 'Quitada' : ($isPartial ? 'Parcial' : ($invoiceItem->due_on->isPast() ? 'Vencida' : 'A vencer')) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Vence em {{ $invoiceItem->due_on->format('d/m/Y') }}</p>
                        <p class="mt-3 text-xl font-semibold tabular-nums text-slate-900">
                            R$ {{ \App\Support\FinancialBalance::formatted($row['remaining_cents']) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Em aberto · {{ $invoiceItem->installments->count() }} parcela(s)</p>
                        @if ($row['carry_cents'] > 0)
                            <p class="mt-2 text-xs text-amber-700">
                                Inclui saldo anterior projetado: R$ {{ \App\Support\FinancialBalance::formatted($row['carry_cents']) }}
                            </p>
                        @endif
                    </button>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-7 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">
                        Nenhuma fatura ainda. Registre uma compra para gerar as parcelas.
                    </div>
                @endforelse
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

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-lg font-semibold">Compras recentes · {{ $card->name }}</h2>
            <div class="mt-3 divide-y divide-slate-100">
                @forelse ($recentPurchases as $purchase)
                    <div wire:key="card-purchase-line-{{ $purchase->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $purchase->description }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $purchase->purchased_on->format('d/m/Y') }} · {{ $purchase->installments_count === 1 ? 'À vista · 1 de 1' : $purchase->installments_count.' parcelas' }}
                            </p>
                        </div>
                        <span class="font-semibold tabular-nums">R$ {{ number_format((float) $purchase->amount, 2, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Nenhuma compra registrada.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
