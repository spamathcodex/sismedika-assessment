// app/Services/OrderService.php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(array $data): Order
    {
        $orderData = [
            'order_number' => $this->generateOrderNumber(),
            'user_id' => auth()->id(),
            'table_id' => $data['table_id'],
            'notes' => $data['notes'] ?? null,
            'order_date' => now(),
            'subtotal' => 0,
            'tax' => 0,
            'service_charge' => 0,
            'total' => 0,
            'status' => 'open',
            'payment_status' => 'unpaid'
        ];

        $order = Order::create($orderData);

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->addOrderItem($order, $item);
            }
        }

        return $order;
    }

    public function updateOrder(Order $order, array $data): Order
    {
        $order->update([
            'notes' => $data['notes'] ?? $order->notes,
            'table_id' => $data['table_id'] ?? $order->table_id
        ]);

        return $order;
    }

    public function addOrderItem(Order $order, array $data): OrderItem
    {
        $menuItem = MenuItem::findOrFail($data['menu_item_id']);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'menu_item_name' => $menuItem->name,
            'price' => $menuItem->price,
            'quantity' => $data['quantity'],
            'modifiers' => $data['modifiers'] ?? null,
            'notes' => $data['notes'] ?? null,
            'subtotal' => $menuItem->price * $data['quantity'],
            'status' => 'pending'
        ]);

        $this->recalculateOrderTotal($order);

        return $orderItem;
    }

    public function removeOrderItem(Order $order, int $itemId): void
    {
        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $orderItem->delete();

        $this->recalculateOrderTotal($order);
    }

    public function processPayment(Order $order, array $data): Order
    {
        $total = $order->total;
        $paidAmount = $data['paid_amount'];
        $changeAmount = max(0, $paidAmount - $total);

        $order->update([
            'payment_status' => 'paid',
            'status' => 'paid',
            'payment_method' => $data['payment_method'],
            'paid_amount' => $paidAmount,
            'change_amount' => $changeAmount,
            'cashier_id' => auth()->id(),
            'completed_at' => now()
        ]);

        return $order;
    }

    protected function recalculateOrderTotal(Order $order): void
    {
        $subtotal = $order->orderItems()->sum('subtotal');
        $tax = $subtotal * 0.11; // 11% tax
        $serviceCharge = $subtotal * 0.05; // 5% service charge
        $total = $subtotal + $tax + $serviceCharge;

        $order->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'service_charge' => $serviceCharge,
            'total' => $total
        ]);
    }

    protected function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));

        return "ORD-{$date}-{$random}";
    }
}
