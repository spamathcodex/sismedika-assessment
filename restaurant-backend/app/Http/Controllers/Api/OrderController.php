// app/Http/Controllers/Api/OrderController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Table;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $orders = Order::with(['table', 'user', 'orderItems.menuItem'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->date_from, function ($query, $date) {
                return $query->whereDate('order_date', '>=', $date);
            })
            ->when($request->date_to, function ($query, $date) {
                return $query->whereDate('order_date', '<=', $date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            DB::beginTransaction();

            // Check if table is available
            $table = Table::findOrFail($request->table_id);
            if ($table->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'Table is not available'
                ], 422);
            }

            $order = $this->orderService->createOrder($request->validated());

            // Update table status
            $table->update(['status' => 'occupied']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order->load(['table', 'user', 'orderItems'])),
                'message' => 'Order created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Order $order)
    {
        return new OrderResource($order->load(['table', 'user', 'orderItems.menuItem.category']));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        try {
            DB::beginTransaction();

            $order = $this->orderService->updateOrder($order, $request->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order->load(['table', 'user', 'orderItems'])),
                'message' => 'Order updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addItem(Request $request, Order $order)
    {
        $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
            'modifiers' => 'nullable|array'
        ]);

        try {
            DB::beginTransaction();

            $orderItem = $this->orderService->addOrderItem($order, $request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $orderItem,
                'message' => 'Item added successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeItem(Order $order, $itemId)
    {
        try {
            DB::beginTransaction();

            $this->orderService->removeOrderItem($order, $itemId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function processPayment(Request $request, Order $order)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,card,qris,other',
            'paid_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $order = $this->orderService->processPayment($order, $request->all());

            // Update table status back to available
            $order->table->update(['status' => 'available']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'Payment processed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateReceipt(Order $order)
    {
        $order->load(['table', 'user', 'orderItems.menuItem', 'cashier']);

        $pdf = PDF::loadView('pdf.receipt', [
            'order' => $order,
            'restaurant' => [
                'name' => 'Restaurant Name',
                'address' => 'Restaurant Address',
                'phone' => 'Restaurant Phone',
                'tax_number' => 'Tax Number'
            ]
        ]);

        return $pdf->download("receipt-{$order->order_number}.pdf");
    }

    public function closeOrder(Order $order)
    {
        try {
            DB::beginTransaction();

            $order->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order closed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to close order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
