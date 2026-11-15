@extends('layouts.app')

@section('title', 'Visão geral')

@section('content')
    <p class="text-sm font-semibold text-sky-700">Visão geral</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Olá, {{ auth()->user()->name }}!</h1>
    <p class="mt-3 max-w-2xl text-base leading-7 text-slate-500">Suas contas, gastos pagos, recebimentos confirmados e transferências entre contas.</p>

    <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Contas financeiras</h2>
                <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Disponível</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Acompanhe os saldos estimados de suas contas financeiras.</p>
            <a href="{{ route('contas.index') }}" class="mt-5 inline-flex rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold transition hover:bg-slate-100">Gerenciar contas</a>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Gastos diários</h2>
                <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Disponível</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Lance somente pagamentos já realizados.</p>
            <a href="{{ route('gastos.index') }}" class="mt-5 inline-flex rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold transition hover:bg-slate-100">Lançar gasto</a>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Recebimentos</h2>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Etapa 04</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Registre salário e outras entradas somente depois de receber.</p>
            <a href="{{ route('recebimentos.index') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Registrar entrada</a>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Cartões</h2>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Etapa 06</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Compras 1/N, faturas, juros manuais e pagamentos parciais.</p>
            <a href="{{ route('cartoes.index') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Gerenciar cartões</a>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="font-semibold">Próximos módulos</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Transferências e saques são feitos em Minhas contas. Cartões e faturas já podem ser utilizados; crediários e contas a vencer serão desenvolvidos em etapas próprias.</p>
    </section>
@endsection
