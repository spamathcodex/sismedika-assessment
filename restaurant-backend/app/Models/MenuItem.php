// app/Models/MenuItem.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'price',
        'type', 'image', 'ingredients', 'nutrition_info',
        'is_available', 'is_popular', 'preparation_time', 'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ingredients' => 'array',
        'nutrition_info' => 'array',
        'is_available' => 'boolean',
        'is_popular' => 'boolean',
        'preparation_time' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($menuItem) {
            $menuItem->slug = Str::slug($menuItem->name);
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
