<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'table_id',
        'user_id',
        'cashier_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'status',
        'payment_status',
        'payment_method',
        'payment_details',
        'subtotal',
        'tax',
        'tax_rate',
        'service_charge',
        'service_charge_rate',
        'discount',
        'discount_type',
        'discount_value',
        'total',
        'paid_amount',
        'change_amount',
        'notes',
        'internal_notes',
        'order_date',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payment_details' => 'array',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'service_charge_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'order_date' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_OPEN = 'open';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAID = 'paid';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Payment status constants
     */
    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_REFUNDED = 'refunded';

    /**
     * Payment method constants
     */
    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_QRIS = 'qris';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_OTHER = 'other';

    /**
     * Discount type constants
     */
    const DISCOUNT_PERCENTAGE = 'percentage';
    const DISCOUNT_FIXED = 'fixed';

    /**
     * Relasi ke table
     */
    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Relasi ke user (pelayan)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke cashier
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Relasi ke customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relasi ke order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope untuk order yang masih open
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_PROCESSING]);
    }

    /**
     * Scope untuk order yang sudah selesai
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope untuk order yang belum dibayar
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_UNPAID);
    }

    /**
     * Scope untuk order hari ini
     */
    public function scopeToday($query)
    {
        return $query->whereDate('order_date', today());
    }

    /**
     * Scope untuk order berdasarkan tanggal
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    /**
     * Hitung ulang total order
     */
    public function recalculateTotals()
    {
        $this->subtotal = $this->orderItems()->sum('subtotal');
        $this->tax = $this->subtotal * ($this->tax_rate / 100);
        $this->service_charge = $this->subtotal * ($this->service_charge_rate / 100);

        // Apply discount
        $discountAmount = 0;
        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            $discountAmount = $this->subtotal * ($this->discount_value / 100);
        } elseif ($this->discount_type === self::DISCOUNT_FIXED) {
            $discountAmount = $this->discount_value;
        }
        $this->discount = $discountAmount;

        $this->total = $this->subtotal + $this->tax + $this->service_charge - $this->discount;

        $this->save();
    }

    /**
     * Proses pembayaran
     */
    public function processPayment($method, $amount, $details = [])
    {
        $this->payment_method = $method;
        $this->paid_amount = $amount;
        $this->change_amount = max(0, $amount - $this->total);

        if ($this->paid_amount >= $this->total) {
            $this->payment_status = self::PAYMENT_PAID;
            $this->status = self::STATUS_PAID;
            $this->completed_at = now();
        } else {
            $this->payment_status = self::PAYMENT_PARTIAL;
        }

        $this->payment_details = $details;
        $this->save();

        return $this;
    }

    /**
     * Batalkan order
     */
    public function cancel($reason = null)
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();

        // Kembalikan stock jika perlu
        foreach ($this->orderItems as $item) {
            if ($item->menuItem && $item->menuItem->stock !== null) {
                $item->menuItem->increment('stock', $item->quantity);
            }
        }

        return $this;
    }

    /**
     * Cek apakah order bisa diedit
     */
    public function isEditable()
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_PROCESSING]);
    }

    /**
     * Generate order number unik
     */
    public static function generateOrderNumber()
    {
        $date = now()->format('Ymd');
        $lastOrder = self::whereDate('created_at', today())->latest()->first();

        if ($lastOrder) {
            $lastNumber = intval(substr($lastOrder->order_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "ORD-{$date}-{$newNumber}";
    }
}
