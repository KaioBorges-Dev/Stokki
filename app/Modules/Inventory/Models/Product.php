<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The primary key type is UUID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'sku',
        'quantity',
        'batch_number',
        'manufacture_date',
        'expiry_date',
        'safety_stock',
        'image_url',
        'extraction_metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'safety_stock' => 'integer',
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'extraction_metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant workspace user that owns the product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query to only include products expired before current date.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereDate('expiry_date', '<', Carbon::today());
    }

    /**
     * Scope query to products expiring within the specified day window.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        $today = Carbon::today();
        $targetDate = Carbon::today()->addDays($days);

        return $query->whereDate('expiry_date', '>=', $today)
                     ->whereDate('expiry_date', '<=', $targetDate);
    }

    /**
     * Scope query to products at or below safety stock threshold.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'safety_stock');
    }

    /**
     * Get the calculated expiry status badge ('expired', 'expiring_soon', 'safe').
     */
    protected function expiryStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $today = Carbon::today();
                $expiry = Carbon::parse($this->expiry_date);

                if ($expiry->isPast()) {
                    return 'expired';
                }

                if ($expiry->diffInDays($today) <= 30) {
                    return 'expiring_soon';
                }

                return 'safe';
            }
        );
    }
}
