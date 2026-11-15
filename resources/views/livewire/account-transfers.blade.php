<div>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-sm font-semibold text-sky-700">Etapa 05</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Transferências entre contas</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
                Movimente dinheiro que já está disponível entre suas contas. Transferências não são novos recebimentos nem novos gastos.
            </p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @error('reversal')
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="alert">
            {{ $message }}
        </div>
    @enderror

    @unless ($hasEnoughAccounts)
        <div class="mt-6 rounded-xl border border-amber-200 bg-white px-5 py-4 text-sm text-slate-700">
            Você precisa ter pelo menos duas contas ativas para transferir.
            <a class="font-semibold text-sky-700 underline underline-offset-2" href="{{ route('contas.index') }}">Gerenciar minhas contas</a>
        </div>
    @endunless

    <section class="mt-8 rounded-2xl border border-sky-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Nova transferência</h2>
                <p class="mt-1 text-sm text-slate-500">O formulário fica sempre disponível para novas movimentações.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Movimentação interna</span>
        </div>

        <form wire:submit="save" wire:key="transfer-form-{{ $formVersion }}" class="mt-6 grid gap-5 md:grid-cols-2">
            <div>
                <label for="transfer-origin" class="mb-2 block text-sm font-medium">De qual conta sai?</label>
                <select id="transfer-origin" wire:model.change.live="fromAccountId" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <option value="">Selecione a origem</option>
                    @foreach ($activeAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
                @error('fromAccountId') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
                <p class="mt-2 text-sm font-medium {{ $sourceIsDeficit ? 'text-amber-700' : 'text-slate-600' }}">
                    {{ $sourceIsDeficit ? 'Valor a regularizar: R$' : 'Disponível para transferir: R$' }} {{ $sourceBalance }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Saldo inicial + recebimentos − pagamentos + transferências recebidas − transferências enviadas.</p>
            </div>
            <div>
                <label for="transfer-destination" class="mb-2 block text-sm font-medium">Para qual conta vai?</label>
                <select id="transfer-destination" wire:model="toAccountId" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                    <option value="">Selecione o destino</option>
                    @foreach ($activeAccounts as $account)
                        @if ((string) $account->id !== $fromAccountId)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endif
                    @endforeach
                </select>
                @error('toAccountId') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="transfer-value" class="mb-2 block text-sm font-medium">Valor transferido</label>
                <input id="transfer-value" wire:model="amount" type="text" data-money-mask inputmode="numeric" autocomplete="off"
                    placeholder="R$ 0,00" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium tabular-nums outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('amount') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="transfer-date" class="mb-2 block text-sm font-medium">Data da transferência</label>
                <input id="transfer-date" wire:model="transferredOn" type="date" max="{{ now()->toDateString() }}" required
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('transferredOn') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="transfer-notes" class="mb-2 block text-sm font-medium">Observação (opcional)</label>
                <input id="transfer-notes" wire:model="notes" type="text" maxlength="500"
                    placeholder="Ex.: Sobra do salário enviada para a conta Neon"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
                @error('notes') <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap items-center gap-3 md:col-span-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled(! $hasEnoughAccounts)
                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Confirmar transferência
                </button>
                <button type="button" wire:click="clear"
                    class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium transition hover:bg-slate-100">
                    Limpar campos
                </button>
                <span class="text-xs text-slate-500">O valor não pode ultrapassar o disponível na origem.</span>
            </div>
        </form>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Transferido no período</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">R$ {{ $monthTotal }}</p>
            <p class="mt-2 text-xs text-slate-500">Somente transferências efetivadas; não representa despesa.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Transferências efetivadas</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ $monthCount }}</p>
            <p class="mt-2 text-xs text-slate-500">Transferências estornadas não entram nos totais.</p>
        </div>
    </section>

    <section class="mt-9">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Histórico de transferências</h2>
                <p class="mt-1 text-sm text-slate-500">Toda transferência fica registrada, inclusive após um estorno.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div>
                    <label for="transfer-filter-month" class="mb-1 block text-xs font-medium text-slate-500">Mês</label>
                    <input id="transfer-filter-month" type="month" wire:model.change.live="selectedMonth"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-700 focus:outline-none">
                </div>
                <div>
                    <label for="transfer-filter-account" class="mb-1 block text-xs font-medium text-slate-500">Conta</label>
                    <select id="transfer-filter-account" wire:model.change.live="filterAccountId"
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
                <table class="min-w-[830px] w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-4">Data</th>
                            <th scope="col" class="px-5 py-4">Origem</th>
                            <th scope="col" class="px-5 py-4">Destino</th>
                            <th scope="col" class="px-5 py-4 text-right">Valor</th>
                            <th scope="col" class="px-5 py-4">Situação</th>
                            <th scope="col" class="px-5 py-4 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($history as $transfer)
                            <tr wire:key="transfer-row-{{ $transfer->id }}" class="transition hover:bg-sky-50/50 {{ $transfer->trashed() ? 'bg-slate-50 text-slate-500' : '' }}">
                                <td class="whitespace-nowrap px-5 py-4">{{ $transfer->transferred_on->format('d/m/Y') }}</td>
                                <th scope="row" class="px-5 py-4 font-semibold text-slate-900">{{ $transfer->fromAccount?->name ?? 'Conta indisponível' }}</th>
                                <td class="px-5 py-4">{{ $transfer->toAccount?->name ?? 'Conta indisponível' }}
                                    @if ($transfer->notes)
                                        <p class="mt-1 max-w-xs truncate text-xs text-slate-500" title="{{ $transfer->notes }}">{{ $transfer->notes }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums">R$ {{ number_format((float) $transfer->amount, 2, ',', '.') }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $transfer->trashed() ? 'bg-slate-200 text-slate-600' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $transfer->trashed() ? 'Estornada' : 'Efetivada' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @unless ($transfer->trashed())
                                        <button type="button" wire:click="undo({{ $transfer->id }})"
                                            wire:confirm="Estornar esta transferência? Só será permitido se o saldo atual do destino for suficiente."
                                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold transition hover:bg-slate-100">
                                            Estornar
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <p class="text-lg font-semibold text-slate-900">Nenhuma transferência neste período</p>
                                    <p class="mt-2 text-sm text-slate-500">Escolha duas contas e registre sua primeira transferência.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500 sm:hidden">Deslize a tabela para o lado para ver todas as colunas.</p>
        <div class="mt-5">{{ $history->links() }}</div>
    </section>
</div>
