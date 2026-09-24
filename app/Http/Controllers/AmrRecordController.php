<?php

namespace App\Http\Controllers;

use App\Models\AmrRecord;
use Illuminate\View\View;

class AmrRecordController extends Controller
{
    /**
     * Display the AMR report.
     */
    public function index(): View
    {
        $records = AmrRecord::query()
            ->with('pile.warehouse.branch')
            ->orderBy('warehouse_name')
            ->orderBy('pile_number')
            ->orderBy('trial_number')
            ->get()
            ->groupBy(
                fn (AmrRecord $record): string => $record->pile_id
                    ? 'pile:'.$record->pile_id
                    : implode('|', [
                        $record->warehouse_name,
                        $record->pile_number,
                        $record->variety,
                        $record->aged_months,
                        $record->volume_bags,
                        $record->rice_millers,
                    ]),
            );

        return view('reports.amr', ['recordGroups' => $records]);
    }
}
