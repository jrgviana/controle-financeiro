<div>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-sm font-semibold text-sky-700">Etapa 03</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Gastos diários</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Registre despesas já pagas no PIX, débito ou dinheiro. Cartões de crédito e contas a vencer serão controlados separadamente.</p>
        </div>
        <button type="button" wire:click="openCreate" @disabled(! $hasActiveAccounts)
            class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">+ Novo gasto</button>
    </div>

    @if (session()->has('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="alert">{{ session('error') }}</div>
    @endif
    @unless ($hasActiveAccounts)
        <div class="mt-6 rounded-xl border border-amber-200 bg-white px-5 py-4 text-sm">
            Antes de lançar um gasto, <a class="font-semibold text-sky-700 underline underline-offset-2" href="{{ route('contas.index') }}">cadastre ou reative uma conta</a>.
        </div>
    @endunless

    <section class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Gastos pagos no período</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">R$ {{ $monthTotal }}</p>
            <p class="mt-2 text-xs text-slate-500">Somente despesas efetivamente pagas.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Lançamentos no período</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $monthCount }}</p>
            <p class="mt-2 text-xs text-slate-500">Resumo atualizado pelos filtros abaixo.</p>
        </div>
    </section>

    @if ($formOpen)
        <section class="mt-8 rounded-2xl border border-sky-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">{{ $editingId ? 'Editar gasto' : 'Registrar gasto pago' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">O valor será vinculado à conta de onde saiu o dinheiro.</p>
                </div>
                <button type="button" wire:click="cancel" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100">Cancelar</button>
            </div>
            <form wire:submit="save" class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="expense-description" class="mb-2 block text-sm font-medium">Descrição</label>
                    <input id="expense-description" type="text" wire:model="description" maxlength="160" required placeholder="Ex.: Almoço, combustível, farmácia"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    @error('description') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense-amount" class="mb-2 block text-sm font-medium">Valor pago</label>
                    <input id="expense-amount" type="text" wire:model="amount" data-money-mask inputmode="numeric" autocomplete="off" required
                        placeholder="R$ 0,00" aria-describedby="expense-amount-help"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium tabular-nums outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <p id="expense-amount-help" class="mt-1 text-xs text-slate-500">Digite os números: 1250 → R$ 12,50.</p>
                    @error('amount') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense-date" class="mb-2 block text-sm font-medium">Data do pagamento</label>
                    <input id="expense-date" type="date" wire:model="paidOn" max="{{ now()->toDateString() }}" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    @error('paidOn') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense-account" class="mb-2 block text-sm font-medium">Conta utilizada</label>
                    <select id="expense-account" wire:model="accountId" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                        <option value="">Selecione a conta</option>
                        @foreach ($editableAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}{{ $account->active ? '' : ' (desativada)' }}</option>
                        @endforeach
                    </select>
                    @error('accountId') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense-category" class="mb-2 block text-sm font-medium">Categoria</label>
                    <select id="expense-category" wire:model="category" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expense-notes" class="mb-2 block text-sm font-medium">Observação (opcional)</label>
                    <textarea id="expense-notes" wire:model="notes" maxlength="500" rows="2" placeholder="Detalhes para lembrar"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100"></textarea>
                    @error('notes') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                        {{ $editingId ? 'Salvar alterações' : 'Registrar gasto' }}
                    </button>
                    <button type="button" wire:click="cancel" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium hover:bg-slate-100">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    <section class="mt-9">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Histórico de gastos pagos</h2>
                <p class="mt-1 text-sm text-slate-500">Valores apresentados sem sinal negativo.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div>
                    <label for="expense-filter-month" class="mb-1 block text-xs font-medium text-slate-500">Mês</label>
                    <input id="expense-filter-month" type="month" wire:model.change.live="selectedMonth"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                </div>
                <div>
                    <label for="expense-filter-account" class="mb-1 block text-xs font-medium text-slate-500">Conta</label>
                    <select id="expense-filter-account" wire:model.change.live="filterAccountId"
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
                            <th scope="col" class="px-5 py-4">Categoria</th>
                            <th scope="col" class="px-5 py-4">Conta</th>
                            <th scope="col" class="px-5 py-4 text-right">Valor pago</th>
                            <th scope="col" class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($expenses as $expense)
                            <tr wire:key="expense-row-{{ $expense->id }}" class="transition hover:bg-sky-50/50">
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $expense->paid_on->format('d/m/Y') }}</td>
                                <th scope="row" class="px-5 py-4 font-semibold text-slate-900">
                                    {{ $expense->description }}
                                    @if ($expense->notes)
                                        <p class="mt-1 max-w-xs truncate text-xs font-normal text-slate-500" title="{{ $expense->notes }}">{{ $expense->notes }}</p>
                                    @endif
                                </th>
                                <td class="px-5 py-4 text-slate-600">{{ $categories[$expense->category] ?? 'Outros' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $expense->financialAccount?->name ?? 'Conta indisponível' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-slate-900">R$ {{ number_format((float) $expense->amount, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="edit({{ $expense->id }})" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                                        <button type="button" wire:click="delete({{ $expense->id }})"
                                            wire:confirm="Remover este gasto? Ele sairá dos totais, mas o registro será preservado."
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Remover</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <p class="text-lg font-semibold text-slate-900">Nenhum gasto neste período</p>
                                    <p class="mt-2 text-sm text-slate-500">Altere os filtros ou registre um pagamento já realizado.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500 sm:hidden">Deslize para o lado para ver todas as colunas.</p>
        <div class="mt-5">{{ $expenses->links() }}</div>
    </section>
</div>
