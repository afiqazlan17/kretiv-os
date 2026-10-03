<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Lalamove pickup from a supplier to Kretivco, possibly combining several jobs (see DeliveryController). */
class DeliveryTrip extends Model
{
    public const STATUSES = ['booked' => 'Booked', 'picked_up' => 'Picked up', 'arrived' => 'Arrived'];

    protected $fillable = ['pickup_vendor_id', 'trip_date', 'actual_cost', 'status', 'receipt_path', 'paid_bank', 'paid_date', 'created_by'];

    protected function casts(): array
    {
        return ['trip_date' => 'date', 'paid_date' => 'date', 'actual_cost' => 'decimal:2'];
    }

    public function pickupVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'pickup_vendor_id');
    }
}
