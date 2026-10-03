<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportColumnSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'visible_columns',
    ];

    protected function casts(): array
    {
        return [
            'visible_columns' => 'array',
        ];
    }

    /**
     * Get the visible columns array for a given report type ('amr' or 'pmr').
     *
     * @return array<string>
     */
    public static function forReport(string $type): array
    {
        $setting = static::where('report_type', $type)->first();
        if ($setting && is_array($setting->visible_columns)) {
            return $setting->visible_columns;
        }

        return static::defaultColumns($type);
    }

    /**
     * Set and persist visible columns for a report type.
     *
     * @param  array<string>  $columns
     */
    public static function setForReport(string $type, array $columns): self
    {
        return static::updateOrCreate(
            ['report_type' => $type],
            ['visible_columns' => array_values(array_unique($columns))]
        );
    }

    /**
     * Get default visible columns for a report type.
     *
     * @return array<string>
     */
    public static function defaultColumns(string $type): array
    {
        return array_keys(static::availableColumns($type));
    }

    /**
     * Get all toggleable columns definition with labels and default states.
     *
     * @return array<string, array{label: string, category: string, description: string}>
     */
    public static function availableColumns(string $type): array
    {
        if ($type === 'amr') {
            return [
                'no' => ['label' => 'No.', 'category' => 'General', 'description' => 'Sequential item counter'],
                'branch' => ['label' => 'Branch', 'category' => 'Location', 'description' => 'Branch office name'],
                'warehouse' => ['label' => 'Warehouse', 'category' => 'Location', 'description' => 'Storage warehouse name'],
                'pile_number' => ['label' => 'Pile No.', 'category' => 'Location', 'description' => 'Grain pile identification number'],
                'variety' => ['label' => 'Variety', 'category' => 'Grains', 'description' => 'Palay variety type'],
                'purity' => ['label' => 'Purity (%)', 'category' => 'Grains', 'description' => 'Percentage of clean whole grains'],
                'mc' => ['label' => 'MC (%)', 'category' => 'Grains', 'description' => 'Moisture content percentage'],
                'quality' => ['label' => 'Quality', 'category' => 'Grains', 'description' => 'Grain condition / quality grade'],
                'aged_months' => ['label' => 'Aged (mos)', 'category' => 'Grains', 'description' => 'Number of months palay has been stored'],
                'volume_bags' => ['label' => 'Volume (bags)', 'category' => 'Grains', 'description' => 'Stock quantity in 50kg bags'],
                'rice_miller' => ['label' => 'Rice Miller', 'category' => 'Milling Details', 'description' => 'Designated commercial miller contractor'],
                'trial' => ['label' => 'Trial', 'category' => 'Milling Details', 'description' => 'Test milling trial number (1-3)'],
                'palay_input' => ['label' => 'Palay In (kg)', 'category' => 'Milling Details', 'description' => 'Initial palay grain input in kilograms'],
                'rice_recovery' => ['label' => 'Rice Rec (kg)', 'category' => 'Milling Details', 'description' => 'Milled rice recovered in kilograms'],
                'recovery_rate' => ['label' => 'Recovery (%)', 'category' => 'Results', 'description' => 'Per-trial milling recovery percentage'],
                'mean' => ['label' => 'Mean (%)', 'category' => 'Results', 'description' => 'Average recovery rate across valid trials'],
                'amr_rate' => ['label' => 'AMR (%)', 'category' => 'Results', 'description' => 'Final established Actual Milling Recovery rate'],
                'status' => ['label' => 'Status', 'category' => 'Results', 'description' => 'Workflow status badge'],
                'actions' => ['label' => 'Actions', 'category' => 'Actions', 'description' => 'Interactive action buttons'],
            ];
        }

        return [
            'no' => ['label' => 'No.', 'category' => 'General', 'description' => 'Sequential item counter'],
            'branch' => ['label' => 'Branch', 'category' => 'Location', 'description' => 'Branch office name'],
            'warehouse' => ['label' => 'Warehouse', 'category' => 'Location', 'description' => 'Storage warehouse name'],
            'pile_number' => ['label' => 'Pile No.', 'category' => 'Location', 'description' => 'Grain pile identification number'],
            'variety' => ['label' => 'Variety', 'category' => 'Grains', 'description' => 'Palay variety type'],
            'purity' => ['label' => 'Purity (%)', 'category' => 'Grains', 'description' => 'Percentage of clean whole grains'],
            'mc' => ['label' => 'MC (%)', 'category' => 'Grains', 'description' => 'Moisture content percentage'],
            'quality' => ['label' => 'Quality', 'category' => 'Grains', 'description' => 'Grain condition / quality grade'],
            'aged_months' => ['label' => 'Aged (mos)', 'category' => 'Grains', 'description' => 'Number of months palay has been stored'],
            'volume_bags' => ['label' => 'Volume (bags)', 'category' => 'Grains', 'description' => 'Stock quantity in 50kg bags'],
            'trial' => ['label' => 'Trial', 'category' => 'Milling Details', 'description' => 'No. of potential milling trial'],
            'recovery_rate' => ['label' => 'Recovery Rate (%)', 'category' => 'Results', 'description' => 'Individual trial recovery percentage'],
            'mean' => ['label' => 'Mean (%)', 'category' => 'Results', 'description' => 'Average recovery rate across valid trials'],
            'pmr_rate' => ['label' => 'PMR (%)', 'category' => 'Results', 'description' => 'Final Potential Milling Recovery rate'],
            'status' => ['label' => 'Status', 'category' => 'Results', 'description' => 'Workflow status badge'],
            'actions' => ['label' => 'Actions', 'category' => 'Actions', 'description' => 'Interactive action buttons'],
        ];
    }
}
