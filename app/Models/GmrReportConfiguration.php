<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GmrReportConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'region_text',
        'branch_text',
        'visible_columns',
        'paper_size',
        'custom_width',
        'custom_height',
        'custom_unit',
        'orientation',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
        'margin_unit',
    ];

    protected function casts(): array
    {
        return [
            'custom_width' => 'float',
            'custom_height' => 'float',
            'visible_columns' => 'array',
            'margin_top' => 'float',
            'margin_right' => 'float',
            'margin_bottom' => 'float',
            'margin_left' => 'float',
        ];
    }

    /**
     * Get the active configuration singleton or create a default instance.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::createDefault();
    }

    /**
     * Create the default configuration based on the reference report.
     */
    public static function createDefault(): self
    {
        return static::create([
            'title' => 'REPORT ON PRE-MILLING ACTIVITY',
            'subtitle' => 'QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR',
            'region_text' => 'Region XII',
            'branch_text' => 'North Cotabato Branch',
            'paper_size' => 'Long Bond',
            'custom_width' => null,
            'custom_height' => null,
            'custom_unit' => 'in',
            'orientation' => 'portrait',
            'margin_top' => 0.5,
            'margin_right' => 0.5,
            'margin_bottom' => 0.5,
            'margin_left' => 0.5,
            'margin_unit' => 'in',
        ]);
    }

    /**
     * @return array<string, array{label: string, field: string, type: string, alignment: string, bold: bool, width: int}>
     */
    public static function availableReportColumns(): array
    {
        return [
            'warehouse' => [
                'label' => 'Warehouse',
                'field' => 'warehouse',
                'type' => 'text',
                'alignment' => 'left',
                'bold' => true,
                'width' => 20,
            ],
            'pile' => [
                'label' => 'Pile No.',
                'field' => 'pile',
                'type' => 'text',
                'alignment' => 'center',
                'bold' => true,
                'width' => 8,
            ],
            'volume_before_test_milling' => [
                'label' => 'Volume in Bags Before Test Milling',
                'field' => 'volume_before_test_milling_bags',
                'type' => 'bags',
                'alignment' => 'right',
                'bold' => false,
                'width' => 15,
            ],
            'volume_after_test_milling' => [
                'label' => 'Volume in Bags After Test Milling',
                'field' => 'volume_after_test_milling_bags',
                'type' => 'bags',
                'alignment' => 'right',
                'bold' => false,
                'width' => 15,
            ],
            'quality' => [
                'label' => 'Quality',
                'field' => 'quality',
                'type' => 'text',
                'alignment' => 'center',
                'bold' => true,
                'width' => 8,
            ],
            'pmr' => [
                'label' => 'PMR(%)',
                'field' => 'pmr',
                'type' => 'percentage',
                'alignment' => 'center',
                'bold' => false,
                'width' => 8,
            ],
            'amr' => [
                'label' => 'AMR(%)',
                'field' => 'amr',
                'type' => 'percentage',
                'alignment' => 'center',
                'bold' => false,
                'width' => 8,
            ],
            'emr' => [
                'label' => 'EMR(%)',
                'field' => 'emr',
                'type' => 'emr',
                'alignment' => 'center',
                'bold' => true,
                'width' => 9,
            ],
            'gmr' => [
                'label' => 'GMR(%)',
                'field' => 'gmr',
                'type' => 'percentage',
                'alignment' => 'center',
                'bold' => true,
                'width' => 9,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getVisibleReportColumns(): array
    {
        $availableColumns = array_keys(static::availableReportColumns());

        if (! is_array($this->visible_columns)) {
            return $availableColumns;
        }

        $visibleColumns = array_values(
            array_intersect($availableColumns, $this->visible_columns),
        );

        return $visibleColumns === [] ? $availableColumns : $visibleColumns;
    }

    /**
     * Returns the CSS @page size declaration value.
     */
    public function getCssPageSize(): string
    {
        $orientation = strtolower($this->orientation ?? 'portrait');

        if (
            $this->paper_size === 'Custom' &&
            $this->custom_width &&
            $this->custom_height
        ) {
            $unit = $this->custom_unit ?: 'in';

            return "{$this->custom_width}{$unit} {$this->custom_height}{$unit} {$orientation}";
        }

        return match ($this->paper_size) {
            'A4' => "A4 {$orientation}",
            'Letter', 'Short Bond' => "8.5in 11in {$orientation}",
            'Legal' => "8.5in 14in {$orientation}",
            'Long Bond' => "8.5in 13in {$orientation}",
            default => "8.5in 13in {$orientation}",
        };
    }

    /**
     * Returns the CSS @page margin declaration value.
     */
    public function getCssMargins(): string
    {
        $unit = $this->margin_unit ?: 'in';
        $top = $this->margin_top ?? 0.5;
        $right = $this->margin_right ?? 0.5;
        $bottom = $this->margin_bottom ?? 0.5;
        $left = $this->margin_left ?? 0.5;

        return "{$top}{$unit} {$right}{$unit} {$bottom}{$unit} {$left}{$unit}";
    }

    /**
     * Scale factor from margin_unit to points (pt).
     */
    public function getMarginScale(): float
    {
        return match ($this->margin_unit) {
            'mm' => 72 / 25.4,
            'cm' => 72 / 2.54,
            default => 72.0,
        };
    }

    /**
     * Returns the top margin in points (pt).
     */
    public function getMarginTopPt(): float
    {
        return (float) ($this->margin_top ?? 0.5) * $this->getMarginScale();
    }

    /**
     * Returns the right margin in points (pt).
     */
    public function getMarginRightPt(): float
    {
        return (float) ($this->margin_right ?? 0.5) * $this->getMarginScale();
    }

    /**
     * Returns the bottom margin in points (pt).
     */
    public function getMarginBottomPt(): float
    {
        return (float) ($this->margin_bottom ?? 0.5) * $this->getMarginScale();
    }

    /**
     * Returns the left margin in points (pt).
     */
    public function getMarginLeftPt(): float
    {
        return (float) ($this->margin_left ?? 0.5) * $this->getMarginScale();
    }

    /**
     * Returns paper dimensions in points (pt) for DomPDF or pixel calculations.
     * 1 in = 72 pt, 1 mm = 2.83465 pt, 1 cm = 28.3465 pt
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [0, 0, width_pt, height_pt]
     */
    public function getPaperBounds(): array
    {
        $widthPt = 8.5 * 72; // default 612 pt
        $heightPt = 13.0 * 72; // default 936 pt for long bond

        if (
            $this->paper_size === 'Custom' &&
            $this->custom_width &&
            $this->custom_height
        ) {
            $scale = match ($this->custom_unit) {
                'mm' => 72 / 25.4,
                'cm' => 72 / 2.54,
                default => 72,
            };
            $widthPt = $this->custom_width * $scale;
            $heightPt = $this->custom_height * $scale;
        } else {
            [$widthPt, $heightPt] = match ($this->paper_size) {
                'A4' => [595.28, 841.89],
                'Letter', 'Short Bond' => [612.0, 792.0],
                'Legal' => [612.0, 1008.0],
                'Long Bond' => [612.0, 936.0],
                default => [612.0, 936.0],
            };
        }

        if (strtolower($this->orientation) === 'landscape') {
            return [
                0.0,
                0.0,
                max($widthPt, $heightPt),
                min($widthPt, $heightPt),
            ];
        }

        return [0.0, 0.0, min($widthPt, $heightPt), max($widthPt, $heightPt)];
    }
}
