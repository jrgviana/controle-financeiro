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
            <p class="text-sm font-medium text-slate-500">Saldos iniciais das contas ativas</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">R$ {{ $initialTotal }}</p>
            <p class="mt-2 text-xs text-slate-500">Ainda não inclui movimentações, receitas ou despesas.</p>
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
                    <label for="account-balance" class="mb-2 block text-sm font-medium">Saldo inicial (R$)</label>
                    <input id="account-balance" type="text" wire:model="initialBalance" inputmode="decimal" placeholder="0,00" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-sky-700 focus:ring-4 focus:ring-sky-100">
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

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @forelse ($accounts as $account)
                <article wire:key="account-{{ $account->id }}" class="rounded-2xl border border-slate-200 bg-white p-6 {{ $account->active ? '' : 'opacity-70' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold">{{ $account->name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ ['corrente' => 'Conta corrente', 'poupanca' => 'Poupança', 'carteira' => 'Carteira', 'outras' => 'Outra conta'][$account->type] ?? 'Conta' }}
                                @if ($account->institution) · {{ $account->institution }} @endif
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $account->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $account->active ? 'Ativa' : 'Desativada' }}
                        </span>
                    </div>
                    <div class="mt-6">
                        <p class="text-xs font-medium text-slate-500">Saldo inicial</p>
                        <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums">R$ {{ number_format((float) $account->initial_balance, 2, ',', '.') }}</p>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="edit({{ $account->id }})" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">Editar</button>
                        <button type="button" wire:click="toggleActive({{ $account->id }})" wire:confirm="{{ $account->active ? 'Desativar esta conta? Os dados serão preservados.' : 'Reativar esta conta?' }}"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-100">
                            {{ $account->active ? 'Desativar' : 'Reativar' }}
                        </button>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center md:col-span-2">
                    <h3 class="text-lg font-semibold">Nenhuma conta cadastrada</h3>
                    <p class="mt-2 text-sm text-slate-500">Comece cadastrando uma conta bancária ou sua carteira.</p>
                    <button type="button" wire:click="openCreate" class="mt-5 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Cadastrar primeira conta</button>
                </div>
            @endforelse
        </div>
    </section>
</div>
