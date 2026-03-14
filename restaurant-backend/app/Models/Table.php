<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tables';

    protected $fillable = [
        'table_number',
        'status',
        'capacity',
        'qr_code',
        'notes',
        'location',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'status' => 'string',
    ];

    /**
     * Status constants
     */
    const STATUS_AVAILABLE = 'available';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_RESERVED = 'reserved';
    const STATUS_MAINTENANCE = 'maintenance';

    /**
     * Relasi ke orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /**
     * Relasi ke order yang sedang aktif
     */
    public function currentOrder()
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereIn('status', ['open', 'processing'])
            ->latest();
    }

    /**
     * Scope untuk meja yang tersedia
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
            ->where('is_active', true);
    }

    /**
     * Scope untuk meja yang terisi
     */
    public function scopeOccupied($query)
    {
        return $query->where('status', self::STATUS_OCCUPIED);
    }

    /**
     * Cek apakah meja tersedia
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->is_active;
    }

    /**
     * Update status meja
     */
    public function updateStatus(string $status): bool
    {
        if (!in_array($status, [
            self::STATUS_AVAILABLE,
            self::STATUS_OCCUPIED,
            self::STATUS_RESERVED,
            self::STATUS_MAINTENANCE
        ])) {
            return false;
        }

        return $this->update(['status' => $status]);
    }

    /**
     * Generate QR Code untuk meja
     */
    public function generateQrCode()
    {
        // Implementasi QR code generation
        $this->qr_code = "table-{$this->table_number}-" . uniqid();
        $this->save();
    }

    /**
     * Get order aktif di meja ini
     */
    public function getActiveOrder()
    {
        return $this->orders()
            ->whereIn('status', ['open', 'processing'])
            ->first();
    }
}
