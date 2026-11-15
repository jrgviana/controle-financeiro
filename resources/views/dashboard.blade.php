@extends('layouts.app')

@section('title', 'Visão geral')

@section('content')
    <p class="text-sm font-semibold text-sky-700">Visão geral</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Olá, {{ auth()->user()->name }}!</h1>
    <p class="mt-3 max-w-2xl text-base leading-7 text-slate-500">Seu controle financeiro está ganhando forma, uma funcionalidade de cada vez.</p>

    <div class="mt-10 grid gap-4 md:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Contas financeiras</h2>
                <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Disponível</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Cadastre suas contas e acompanhe os gastos pagos em cada uma.</p>
            <a href="{{ route('contas.index') }}" class="mt-5 inline-flex rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold transition hover:bg-slate-100">Gerenciar contas</a>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Gastos diários</h2>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Etapa 03</span>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">Registre pagamentos feitos por PIX, débito ou dinheiro, com data, categoria e conta utilizada.</p>
            <a href="{{ route('gastos.index') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Lançar gasto</a>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 class="font-semibold">Próximos módulos</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Cartões de crédito, crediários, contas a vencer e recebimentos serão implementados separadamente. Compromissos ainda não pagos não entram nos gastos diários.</p>
    </section>
@endsection
