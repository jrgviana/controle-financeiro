<div>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-sky-700">Movimentações internas</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Histórico de transferências e saques</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">
                Consulte as movimentações realizadas. Para movimentar dinheiro, use a opção Transferir / Sacar na conta de origem.
            </p>
        </div>
        <a href="{{ route('contas.index') }}"
           class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
            Voltar às contas
        </a>
    </div>

    <section class="mt-9">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Movimentações realizadas</h2>
                <p class="mt-1 text-sm text-slate-500">Os registros estornados ficam preservados no histórico, sem impactar o saldo.</p>
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
            <div class="hidden grid-cols-[110px_minmax(0,1fr)_minmax(0,1fr)_120px_100px] gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid">
                <span>Data</span>
                <span>Origem</span>
                <span>Destino</span>
                <span class="text-right">Valor</span>
                <span class="text-right">Situação</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($history as $transfer)
                    <div wire:key="transfer-history-{{ $transfer->id }}"
                         class="grid min-w-0 gap-2 px-5 py-4 text-sm md:grid-cols-[110px_minmax(0,1fr)_minmax(0,1fr)_120px_100px] md:items-center md:gap-3 {{ $transfer->trashed() ? 'bg-slate-50 text-slate-500' : '' }}">
                        <span class="text-xs text-slate-500">{{ $transfer->transferred_on->format('d/m/Y') }}</span>
                        <span class="break-words font-semibold text-slate-900">{{ $transfer->fromAccount?->name ?? 'Conta indisponível' }}</span>
                        <div class="min-w-0">
                            <p class="break-words text-slate-700">{{ $transfer->toAccount?->name ?? 'Conta indisponível' }}</p>
                            @if ($transfer->notes)
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $transfer->notes }}</p>
                            @endif
                        </div>
                        <span class="font-semibold tabular-nums md:text-right">
                            R$ {{ number_format((float) $transfer->amount, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-semibold md:text-right {{ $transfer->trashed() ? 'text-slate-500' : 'text-emerald-700' }}">
                            {{ $transfer->trashed() ? 'Estornada' : 'Efetivada' }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <p class="text-lg font-semibold text-slate-900">Nenhuma movimentação neste período</p>
                        <p class="mt-2 text-sm text-slate-500">Altere os filtros ou registre uma transferência pela tela Minhas contas.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="mt-5">{{ $history->links() }}</div>
    </section>
</div>
