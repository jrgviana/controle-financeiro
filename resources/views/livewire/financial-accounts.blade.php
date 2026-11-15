<div>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-sm font-semibold text-sky-700">Etapa 02</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Minhas contas</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Acompanhe apenas o dinheiro disponível, entradas, saídas, transferências e saques. As contas fixas e os cartões têm abas próprias.</p>
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
