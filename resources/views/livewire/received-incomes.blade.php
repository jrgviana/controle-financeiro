<div>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-sm font-semibold text-sky-700">Etapa 04</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Recebimentos</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
                Registre salário e outras entradas somente quando o dinheiro realmente chegar. Valores previstos não aumentam o saldo.
            </p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @unless ($hasActiveAccounts)
        <div class="mt-6 rounded-xl border border-amber-200 bg-white px-5 py-4 text-sm">
            Para registrar um recebimento, <a class="font-semibold text-sky-700 underline underline-offset-2" href="{{ route('contas.index') }}">cadastre ou reative uma conta financeira</a>.
        </div>
    @endunless

    <section class="mt-8 rounded-2xl border border-sky-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ $editingId ? 'Editar recebimento' : 'Registrar dinheiro recebido' }}</h2>
                <p class="mt-1 text-sm text-slate-500">O dinheiro será somado à conta selecionada apenas quando você salvar este recebimento.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Entrada confirmada</span>
        </div>

        <form wire:submit="save" wire:key="income-form-{{ $editingId ?? 'new' }}-{{ $formVersion }}" class="mt-6 grid gap-5 md:grid-cols-2">
            <div>
                <label for="income-description" class="mb-2 block text-sm font-medium">Descrição</label>
                <input id="income-description" type="text" wire:model="description" maxlength="160" required
                    placeholder="Ex.: Salário de novembro, venda de equipamento"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('description') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="income-amount" class="mb-2 block text-sm font-medium">Valor recebido</label>
                <input id="income-amount" type="text" wire:model="amount" data-money-mask inputmode="numeric" autocomplete="off" required
                    placeholder="R$ 0,00" aria-describedby="income-amount-help"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium tabular-nums outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                <p id="income-amount-help" class="mt-1 text-xs text-slate-500">Digite os números: 125000 → R$ 1.250,00.</p>
                @error('amount') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="income-date" class="mb-2 block text-sm font-medium">Data em que recebeu</label>
                <input id="income-date" type="date" wire:model="receivedOn" max="{{ now()->toDateString() }}" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('receivedOn') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="income-account" class="mb-2 block text-sm font-medium">Conta que recebeu</label>
                <select id="income-account" wire:model="accountId" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <option value="">Selecione a conta</option>
                    @foreach ($editableAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}{{ $account->active ? '' : ' (desativada)' }}</option>
                    @endforeach
                </select>
                @error('accountId') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="income-category" class="mb-2 block text-sm font-medium">Tipo de recebimento</label>
                <select id="income-category" wire:model="category" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('category') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="income-notes" class="mb-2 block text-sm font-medium">Observação (opcional)</label>
                <textarea id="income-notes" wire:model="notes" maxlength="500" rows="2" placeholder="Detalhes para lembrar"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100"></textarea>
                @error('notes') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap gap-3 md:col-span-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled(! $hasActiveAccounts && $editingId === null)
                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50">
                    {{ $editingId ? 'Salvar alterações' : 'Registrar recebimento' }}
                </button>
                <button type="button" wire:click="cancel"
                    class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium transition hover:bg-slate-100">
                    {{ $editingId ? 'Cancelar edição' : 'Limpar campos' }}
                </button>
            </div>
        </form>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Entradas recebidas no período</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">R$ {{ $monthTotal }}</p>
            <p class="mt-2 text-xs text-slate-500">Apenas valores efetivamente recebidos.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Recebimentos no período</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $monthCount }}</p>
            <p class="mt-2 text-xs text-slate-500">Os filtros abaixo também atualizam este resumo.</p>
        </div>
    </section>

    <section class="mt-9">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Histórico de recebimentos</h2>
                <p class="mt-1 text-sm text-slate-500">Valores de entrada apresentados sem sinal negativo.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div>
                    <label for="income-filter-month" class="mb-1 block text-xs font-medium text-slate-500">Mês</label>
                    <input id="income-filter-month" type="month" wire:model.change.live="selectedMonth"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                </div>
                <div>
                    <label for="income-filter-account" class="mb-1 block text-xs font-medium text-slate-500">Conta</label>
                    <select id="income-filter-account" wire:model.change.live="filterAccountId"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                        <option value="">Todas as contas</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[860px] w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-4">Data</th>
                            <th scope="col" class="px-5 py-4">Descrição</th>
                            <th scope="col" class="px-5 py-4">Tipo</th>
                            <th scope="col" class="px-5 py-4">Conta</th>
                            <th scope="col" class="px-5 py-4 text-right">Valor recebido</th>
                            <th scope="col" class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($incomes as $income)
                            <tr wire:key="income-row-{{ $income->id }}" class="transition hover:bg-sky-50/50">
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $income->received_on->format('d/m/Y') }}</td>
                                <th scope="row" class="px-5 py-4 font-semibold text-slate-900">
                                    {{ $income->description }}
                                    @if ($income->notes)
                                        <p class="mt-1 max-w-xs truncate text-xs font-normal text-slate-500" title="{{ $income->notes }}">{{ $income->notes }}</p>
                                    @endif
                                </th>
                                <td class="px-5 py-4 text-slate-600">{{ $categories[$income->category] ?? 'Outros' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $income->financialAccount?->name ?? 'Conta indisponível' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-slate-900">R$ {{ number_format((float) $income->amount, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="edit({{ $income->id }})" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                                        <button type="button" wire:click="delete({{ $income->id }})"
                                            wire:confirm="Remover este recebimento? Ele deixará de contar nos totais, mas o registro será preservado."
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Remover</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <p class="text-lg font-semibold text-slate-900">Nenhum recebimento neste período</p>
                                    <p class="mt-2 text-sm text-slate-500">Registre uma entrada já recebida ou altere os filtros.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500 sm:hidden">Deslize para o lado para ver todas as colunas.</p>
        <div class="mt-5">{{ $incomes->links() }}</div>
    </section>
</div>
