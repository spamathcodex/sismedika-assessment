// app/Http/Requests/StoreOrderRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasRole('pelayan');
    }

    public function rules(): array
    {
        return [
            'table_id' => 'required|exists:tables,id',
            'notes' => 'nullable|string|max:500',
            'items' => 'sometimes|array',
            'items.*.menu_item_id' => 'required_with:items|exists:menu_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.modifiers' => 'nullable|array'
        ];
    }

    public function messages(): array
    {
        return [
            'table_id.required' => 'Please select a table',
            'table_id.exists' => 'Selected table does not exist',
            'items.*.menu_item_id.required_with' => 'Please select a menu item',
            'items.*.quantity.min' => 'Quantity must be at least 1'
        ];
    }
}
