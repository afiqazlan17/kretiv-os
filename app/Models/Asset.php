<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'purchase_date', 'cost', 'bank', 'receipt_path', 'receipt_name', 'ledger_entry_id', 'disposed_on', 'notes'])]
class Asset extends Model
{
    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'disposed_on' => 'date', 'cost' => 'decimal:2'];
    }

    public function isSmallValue(): bool
    {
        return (float) $this->cost <= (float) config('kretivco.capital_allowance.small_value_limit');
    }

    /**
     * Capital allowance claimable for a year of assessment (calendar year),
     * and the tax written-down value left after it. Indicative: initial +
     * annual allowance in the purchase year, annual allowance after, small
     * value assets 100% up front; no allowance from the year it's disposed.
     *
     * @return array{allowance: float, twdv: float}
     */
    public function capitalAllowance(int $year): array
    {
        $cost = (float) $this->cost;
        $bought = $this->purchase_date->year;
        $rates = config("kretivco.capital_allowance.categories.{$this->category}", ['ia' => 20, 'aa' => 10]);

        $claimed = 0.0;
        $allowance = 0.0;
        for ($y = $bought; $y <= $year; $y++) {
            $due = $this->isSmallValue()
                ? ($y === $bought ? $cost : 0.0)
                : $cost * (($y === $bought ? $rates['ia'] : 0) + $rates['aa']) / 100;
            if ($this->disposed_on && $this->disposed_on->year <= $y) {
                $due = 0.0;
            }
            $due = round(min($due, $cost - $claimed), 2);
            $claimed += $due;
            $allowance = $y === $year ? $due : 0.0;
        }

        return ['allowance' => $year < $bought ? 0.0 : $allowance, 'twdv' => round($cost - $claimed, 2)];
    }
}
