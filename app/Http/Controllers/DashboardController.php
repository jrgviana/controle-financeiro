<?php

namespace App\Http\Controllers;

use App\Support\DashboardOverview;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardOverview $overview): View
    {
        $now = CarbonImmutable::today()->startOfMonth();
        $minimum = $now->subMonths(24);
        $maximum = $now->addMonths(24);

        $requested = $request->query('period');
        $month = $now;

        // Não permitir meses arbitrariamente distantes: uma recorrência antiga
        // poderia gerar milhares de iterações no cálculo dos compromissos.
        if (is_string($requested)
            && preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $requested)) {
            $candidate = CarbonImmutable::parse($requested.'-01');
            if ($candidate->betweenIncluded($minimum, $maximum)) {
                $month = $candidate;
            }
        }

        return view('dashboard', [
            'dashboard' => $overview->build((int) $request->user()->id, $month),
            'selectedMonth' => $month->format('Y-m'),
            'periodOptions' => collect(range(-12, 12))->map(function (int $offset) use ($now): array {
                $date = $now->addMonths($offset);
                return [
                    'value' => $date->format('Y-m'),
                    'label' => ucfirst($date->locale('pt_BR')->translatedFormat('M/Y')),
                ];
            }),
        ]);
    }
}
