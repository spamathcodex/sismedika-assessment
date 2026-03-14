<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'birth_date',
        'gender',
        'notes',
        'is_member',
        'member_number',
        'points',
        'total_spent',
        'last_visit',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_member' => 'boolean',
        'points' => 'integer',
        'total_spent' => 'decimal:2',
        'last_visit' => 'datetime',
    ];

    /**
     * Relasi ke orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope untuk member
     */
    public function scopeMembers($query)
    {
        return $query->where('is_member', true);
    }

    /**
     * Hitung total pesanan
     */
    public function getTotalOrdersAttribute()
    {
        return $this->orders()->count();
    }

    /**
     * Generate member number
     */
    public static function generateMemberNumber()
    {
        $prefix = 'MEM';
        $year = date('Y');
        $month = date('m');
        $lastCustomer = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->latest()
            ->first();

        if ($lastCustomer) {
            $lastNumber = intval(substr($lastCustomer->member_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}{$year}{$month}{$newNumber}";
    }
}
