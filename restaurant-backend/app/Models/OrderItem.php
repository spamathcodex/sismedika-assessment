<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_name',
        'price',
        'quantity',
        'modifiers',
        'notes',
        'status',
        'cooked_by',
        'cooked_at',
        'served_by',
        'served_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'subtotal',
    ];

    protected $casts = [
        'modifiers' => 'array',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'cooked_at' => 'datetime',
        'served_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relasi ke order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relasi ke menu item
     */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Relasi ke cook
     */
    public function cook()
    {
        return $this->belongsTo(User::class, 'cooked_by');
    }

    /**
     * Relasi ke server
     */
    public function server()
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    /**
     * Relasi ke canceller
     */
    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Scope untuk item pending
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope untuk item dalam persiapan
     */
    public function scopePreparing($query)
    {
        return $query->where('status', self::STATUS_PREPARING);
    }

    /**
     * Scope untuk item siap saji
     */
    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    /**
     * Mulai memasak item
     */
    public function startCooking($cookId)
    {
        $this->status = self::STATUS_PREPARING;
        $this->cooked_by = $cookId;
        $this->cooked_at = now();
        $this->save();
    }

    /**
     * Tandai item siap
     */
    public function markAsReady()
    {
        $this->status = self::STATUS_READY;
        $this->save();
    }

    /**
     * Tandai item sudah disajikan
     */
    public function markAsServed($serverId)
    {
        $this->status = self::STATUS_SERVED;
        $this->served_by = $serverId;
        $this->served_at = now();
        $this->save();
    }

    /**
     * Batalkan item
     */
    public function cancel($userId, $reason = null)
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_by = $userId;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();

        // Update order total
        $this->order->recalculateTotals();
    }

    /**
     * Update quantity
     */
    public function updateQuantity($newQuantity)
    {
        $oldQuantity = $this->quantity;
        $this->quantity = $newQuantity;
        $this->subtotal = $this->price * $newQuantity;
        $this->save();

        // Update stock jika perlu
        if ($this->menuItem && $this->menuItem->stock !== null) {
            $difference = $newQuantity - $oldQuantity;
            if ($difference > 0) {
                $this->menuItem->decreaseStock($difference);
            } elseif ($difference < 0) {
                $this->menuItem->increment('stock', abs($difference));
            }
        }

        // Update order total
        $this->order->recalculateTotals();
    }

    /**
     * Get estimated preparation time
     */
    public function getEstimatedTimeAttribute()
    {
        if ($this->menuItem && $this->menuItem->preparation_time) {
            return $this->menuItem->preparation_time * $this->quantity;
        }
        return null;
    }
}
