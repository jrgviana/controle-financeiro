<div>
    <div>
        <p class="text-sm font-semibold text-sky-700">Lançamentos financeiros</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Gastos</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500">
            Um único lugar para registrar pagamentos realizados e compras no cartão.
            Pagamentos reduzem o saldo da conta; compras no cartão entram nas faturas, sem desconto bancário antecipado.
        </p>
    </div>

    @if (session()->has('success'))
        <div role="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div role="alert" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('error') }}</div>
    @endif

    <section class="mt-8 rounded-2xl border border-sky-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ $editingId ? 'Editar gasto' : 'Registrar gasto' }}</h2>
                <p class="mt-1 text-sm text-slate-500">Selecione a forma de pagamento. O formulário muda para os dados necessários.</p>
            </div>
        </div>

        <fieldset class="mt-5">
            <legend class="mb-2 text-sm font-semibold text-slate-900">Modalidade do lançamento</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 text-sm transition {{ $paymentMethod === 'account' ? 'border-sky-400 bg-sky-50' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="radio" name="payment-method" wire:model.change.live="paymentMethod" value="account" class="mt-1 accent-slate-900">
                    <span>
                        <strong class="block text-slate-900">Pagamento imediato</strong>
                        <span class="mt-1 block text-xs text-slate-500">PIX, débito ou dinheiro · já saiu da conta</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 text-sm transition {{ $paymentMethod === 'card' ? 'border-sky-400 bg-sky-50' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="radio" name="payment-method" wire:model.change.live="paymentMethod" value="card" class="mt-1 accent-slate-900">
                    <span>
                        <strong class="block text-slate-900">Compra no cartão</strong>
                        <span class="mt-1 block text-xs text-slate-500">À vista 1/1 ou parcelada · gera faturas</span>
                    </span>
                </label>
            </div>
        </fieldset>

        @if ($paymentMethod === 'account' && ! $hasActiveAccounts && $editingId === null)
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Para lançar pagamentos, <a href="{{ route('contas.index') }}" class="font-semibold underline">cadastre ou reative uma conta financeira</a>.
            </p>
        @elseif ($paymentMethod === 'card' && ! $hasActiveCards)
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Para lançar compras no cartão, <a href="{{ route('cartoes.index') }}" class="font-semibold underline">cadastre ou reative um cartão</a>.
            </p>
        @endif

        <form wire:submit="save" wire:key="spending-form-{{ $editingId ?? 'new' }}-{{ $formVersion }}"
              class="mt-6 grid gap-4 md:grid-cols-2">
            <div>
                <label for="expense-description" class="mb-1 block text-sm font-medium">Descrição</label>
                <input id="expense-description" type="text" wire:model="description" maxlength="160" required
                    placeholder="Ex.: Almoço, combustível, mercado"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('description') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expense-amount" class="mb-1 block text-sm font-medium">
                    {{ $paymentMethod === 'card' ? 'Valor total da compra' : 'Valor pago' }}
                </label>
                <input id="expense-amount" type="text" wire:model="amount" data-money-mask inputmode="numeric" autocomplete="off" required
                    placeholder="R$ 0,00"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium tabular-nums outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('amount') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expense-date" class="mb-1 block text-sm font-medium">
                    {{ $paymentMethod === 'card' ? 'Data da compra' : 'Data do pagamento' }}
                </label>
                <input id="expense-date" type="date" wire:model="paidOn" max="{{ now()->toDateString() }}" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('paidOn') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            @if ($paymentMethod === 'card')
                <div>
                    <label for="expense-card" class="mb-1 block text-sm font-medium">Cartão utilizado</label>
                    <select id="expense-card" wire:model="cardId" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                        <option value="">Selecione o cartão</option>
                        @foreach ($activeCards as $creditCard)
                            <option value="{{ $creditCard->id }}">{{ $creditCard->name }} · fecha dia {{ $creditCard->closing_day }}, vence dia {{ $creditCard->due_day }}</option>
                        @endforeach
                    </select>
                    @error('cardId') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense-installments" class="mb-1 block text-sm font-medium">Número de parcelas</label>
                    <input id="expense-installments" type="number" wire:model="installmentsCount" min="1" max="60" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <p class="mt-1 text-xs text-slate-500">1 = compra à vista (1/1). Parcelas distribuídas automaticamente nas próximas faturas.</p>
                    @error('installmentsCount') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label for="expense-account" class="mb-1 block text-sm font-medium">Conta utilizada</label>
                    <select id="expense-account" wire:model="accountId" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                        <option value="">Selecione a conta</option>
                        @foreach ($editableAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}{{ $account->active ? '' : ' (desativada)' }}</option>
                        @endforeach
                    </select>
                    @error('accountId') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label for="expense-category" class="mb-1 block text-sm font-medium">Categoria</label>
                <select id="expense-category" wire:model="category" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('category') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="expense-notes" class="mb-1 block text-sm font-medium">Observação (opcional)</label>
                <input id="expense-notes" type="text" wire:model="notes" maxlength="500" placeholder="Detalhes para lembrar"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('notes') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap items-center gap-3 md:col-span-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    @disabled($paymentMethod === 'card' ? ! $hasActiveCards : (! $hasActiveAccounts && $editingId === null))
                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ $editingId ? 'Salvar alterações' : ($paymentMethod === 'card' ? 'Registrar compra no cartão' : 'Registrar gasto pago') }}
                </button>
                <button type="button" wire:click="cancel"
                    class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium hover:bg-slate-100">
                    {{ $editingId ? 'Cancelar edição' : 'Limpar campos' }}
                </button>
                @if ($paymentMethod === 'card')
                    <p class="text-xs text-slate-500">A compra compromete as próximas faturas, mas não sai do saldo bancário agora.</p>
                @endif
            </div>
        </form>
    </section>

    <section class="mt-7 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Pagamentos efetivados no mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ $paidTotal }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $paidCount }} gasto(s) pago(s)</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Compras feitas no cartão no mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">R$ {{ $cardTotal }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $cardCount }} compra(s), à vista ou parceladas</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Total de lançamentos no mês</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums">{{ $paidCount + $cardCount }}</p>
            <p class="mt-1 text-xs text-slate-500">Pagamentos e compras não são somados ao mesmo saldo.</p>
        </div>
    </section>

    <section class="mt-9">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Histórico de lançamentos</h2>
                <p class="mt-1 text-sm text-slate-500">Pagamentos e compras no cartão identificados separadamente.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <div>
                    <label for="expense-filter-month" class="mb-1 block text-xs font-medium text-slate-500">Mês</label>
                    <input id="expense-filter-month" type="month" wire:model.change.live="selectedMonth"
                        class="max-w-40 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                </div>
                <div>
                    <label for="expense-filter-type" class="mb-1 block text-xs font-medium text-slate-500">Modalidade</label>
                    <select id="expense-filter-type" wire:model.change.live="filterType"
                        class="max-w-44 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                        <option value="all">Todos</option>
                        <option value="account">Pagos</option>
                        <option value="card">Cartão</option>
                    </select>
                </div>
                @if ($filterType !== 'card')
                    <div>
                        <label for="expense-filter-account" class="mb-1 block text-xs font-medium text-slate-500">Conta</label>
                        <select id="expense-filter-account" wire:model.change.live="filterAccountId"
                            class="max-w-44 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                            <option value="">Todas</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($filterType !== 'account')
                    <div>
                        <label for="expense-filter-card" class="mb-1 block text-xs font-medium text-slate-500">Cartão</label>
                        <select id="expense-filter-card" wire:model.change.live="filterCardId"
                            class="max-w-44 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                            <option value="">Todos</option>
                            @foreach ($cards as $creditCard)
                                <option value="{{ $creditCard->id }}">{{ $creditCard->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        <div wire:key="spending-history-{{ $historyVersion }}" class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid lg:grid-cols-[90px_minmax(0,1.65fr)_minmax(0,1.1fr)_minmax(0,1.05fr)_110px_110px]">
                <span>Data</span><span>Descrição</span><span>Modalidade</span><span>Conta / cartão</span>
                <span class="text-right">Valor</span><span class="text-right">Ações</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($movements as $movement)
                    <div wire:key="spending-{{ $movement['kind'] }}-{{ $movement['id'] }}"
                        class="grid min-w-0 gap-2 px-4 py-4 text-sm transition hover:bg-slate-50/70 lg:grid-cols-[90px_minmax(0,1.65fr)_minmax(0,1.1fr)_minmax(0,1.05fr)_110px_110px] lg:items-center lg:gap-3">
                        <p class="text-xs text-slate-500">{{ $movement['date']->format('d/m/Y') }}</p>
                        <div class="min-w-0">
                            <p class="break-words font-semibold text-slate-900">{{ $movement['description'] }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $categories[$movement['category']] ?? 'Outros' }}
                                @if ($movement['notes']) · {{ $movement['notes'] }} @endif
                            </p>
                        </div>
                        <div>
                            @if ($movement['kind'] === 'card')
                                <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">
                                    Cartão · {{ $movement['installments'] }}x
                                </span>
                                <p class="mt-1 text-xs text-slate-500">{{ $movement['installments'] === 1 ? 'À vista · 1 de 1' : 'Parcelas nas faturas' }}</p>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Pago</span>
                            @endif
                        </div>
                        <p class="break-words text-xs text-slate-600">{{ $movement['origin'] }}</p>
                        <p class="font-semibold tabular-nums lg:text-right">
                            R$ {{ number_format((float) $movement['amount'], 2, ',', '.') }}
                        </p>
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            @if ($movement['kind'] === 'account' && ! ($movement['planned'] ?? false))
                                <button type="button" wire:click="edit({{ $movement['id'] }})"
                                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                                <button type="button" wire:click="delete({{ $movement['id'] }})"
                                    wire:confirm="Remover este gasto? O registro ficará preservado."
                                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Remover</button>
                            @elseif ($movement['kind'] === 'account')
                                <a href="{{ route('contas.index') }}" class="text-xs font-semibold text-sky-700 underline">Conta quitada</a>
                            @else
                                <a href="{{ route('cartoes.index', ['cartao' => $movement['card_id']]) }}"
                                   class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                                    Ver faturas
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="text-lg font-semibold text-slate-900">Nenhum gasto neste período</p>
                        <p class="mt-2 text-sm text-slate-500">Altere os filtros ou registre um gasto ou compra.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="mt-5">{{ $movements->links() }}</div>
    </section>
</div>
