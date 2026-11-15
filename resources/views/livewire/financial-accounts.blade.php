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
            <p class="mt-2 text-xs text-slate-500">Saldo inicial + recebimentos + transferências recebidas − gastos pagos − transferências enviadas. Não inclui compras no cartão e contas futuras.</p>
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
            <h2 class="text-lg font-semibold">Contas cadastradas</h2>
            <span class="text-sm text-slate-500">{{ $accounts->count() }} no total</span>
        </div>


        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[1460px] w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-4">Conta</th>
                            <th scope="col" class="px-5 py-4">Tipo</th>
                            <th scope="col" class="px-5 py-4">Instituição</th>
                            <th scope="col" class="px-5 py-4 text-right">Saldo inicial</th>
                            <th scope="col" class="px-5 py-4 text-right">Recebimentos</th>
                            <th scope="col" class="px-5 py-4 text-right">Gastos pagos</th>
                            <th scope="col" class="px-5 py-4 text-right">Transferências</th>
                            <th scope="col" class="px-5 py-4 text-right">Disponível calculado</th>
                            <th scope="col" class="px-5 py-4">Situação</th>
                            <th scope="col" class="px-5 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($accounts as $account)
                            @php
                                $initialCents = (int) round((float) $account->initial_balance * 100);
                                $receivedCents = (int) round((float) ($account->income_received ?? 0) * 100);
                                $spentCents = (int) round((float) ($account->expenses_paid ?? 0) * 100);
                                $transferInCents = FinancialBalance::cents($account->transfers_received ?? 0);
                                $transferOutCents = FinancialBalance::cents($account->transfers_sent ?? 0);
                                $accountBalanceCents = FinancialBalance::loadedCents($account);
                            @endphp
                            <tr wire:key="account-row-{{ $account->id }}" class="transition hover:bg-sky-50/50 {{ $account->active ? '' : 'bg-slate-50/70 text-slate-500' }}">
                                <th scope="row" class="px-5 py-4 font-semibold text-slate-900">{{ $account->name }}</th>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ ['corrente' => 'Conta corrente', 'poupanca' => 'Poupança', 'carteira' => 'Carteira', 'outras' => 'Outra conta'][$account->type] ?? 'Conta' }}
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $account->institution ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right tabular-nums text-slate-600">R$ {{ number_format((float) $account->initial_balance, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right tabular-nums text-slate-600">R$ {{ number_format($receivedCents / 100, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right tabular-nums text-slate-600">R$ {{ number_format($spentCents / 100, 2, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-xs tabular-nums text-slate-600">
                                    <span class="block">Recebidas: R$ {{ FinancialBalance::formatted($transferInCents) }}</span>
                                    <span class="mt-1 block">Enviadas: R$ {{ FinancialBalance::formatted($transferOutCents) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums text-slate-900">
                                    {{ $accountBalanceCents < 0 ? 'Faltam R$' : 'R$' }} {{ number_format(abs($accountBalanceCents) / 100, 2, ',', '.') }}
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $account->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $account->active ? 'Ativa' : 'Desativada' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="edit({{ $account->id }})" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">Editar</button>
                                        <button type="button" wire:click="toggleActive({{ $account->id }})" wire:confirm="{{ $account->active ? 'Desativar esta conta? Os dados serão preservados.' : 'Reativar esta conta?' }}"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                            {{ $account->active ? 'Desativar' : 'Reativar' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center">
                                    <p class="text-lg font-semibold text-slate-900">Nenhuma conta cadastrada</p>
                                    <p class="mt-2 text-sm text-slate-500">Comece cadastrando uma conta bancária ou sua carteira.</p>
                                    <button type="button" wire:click="openCreate" class="mt-5 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Cadastrar primeira conta</button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500 sm:hidden">Deslize a tabela para o lado para visualizar todas as colunas.</p>
    </section>
</div>
