<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Cloth;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function createTransaction(Request $request): object
    {
        $user = Auth::user();
        try {
            $transaction = Transaction::create([
                'user_id' => $user->id
            ]);
            return ResponseFormatter::success([
                'Transaction' => $transaction,
            ], 'Create transaction success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to create transaction', 500);
        }
    }

    public function createTransactionItem(Request $request): object
    {
        $user = Auth::user();
        try {
            $transaction = Transaction::where('id', $request->transaction_id)->first();
            if (!$transaction) {
                return ResponseFormatter::error([
                    'message' => 'Transaction not found',
                ], 'Failed to create transaction item', 404);
            }

            $cloth = Cloth::where('id', $request->cloth_id)->first();
            if (!$cloth) {
                return ResponseFormatter::error([
                    'message' => 'Cloth not found',
                ], 'Failed to create transaction item', 404);
            }

            $transactionItem = TransactionItem::create([
                'user_id' => $user->id,
                'transaction_id' => $request->transaction_id,
                'cloth_id' => $request->cloth_id,
            ]);
            return ResponseFormatter::success([
                'TransactionItem' => $transactionItem,
            ], 'Create transaction item success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to create transaction item', 500);
        }
    }

    public function updateTransaction(Request $request): object
    {
        $user = Auth::user();
        try {
            $transaction = Transaction::where('id', $request->transaction_id)->first();
            if (!$transaction) {
                return ResponseFormatter::error([
                    'message' => 'Transaction not found',
                ], 'Failed to update transaction', 404);
            }

            $transaction->update([
                'address' => $request->address,
                'weight' => $request->weight,
                'quantity' => $request->quantity,
                'total_selling_price' => $this->calculatePrice($request->weight, $request->quantity),
                'total_pickup_cost' => $request->total_pickup_cost? $request->total_pickup_cost : null,
                'pickup_date' => $request->pickup_date? Carbon::parse($request->pickup_date)->format('Y-m-d H:i:s') : null,
                'status' => $request->status,
                'account_type' => $user->account_type,
                'account_number' => $user->account_number,
            ]);
            return ResponseFormatter::success([
                'Transaction' => $transaction,
            ], 'Update transaction success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to update transaction', 500);
        }
    }

    public function getTransaction(Request $request): object
    {
        // Optional for query parameters
        $limit = $request->input('limit');
        $max_total_selling_price = $request->input('max_total_selling_price');
        $min_total_selling_price = $request->input('min_total_selling_price');
        $pickup_date_range = json_decode($request->input('pickup_date_range'));
        $created_at = json_decode($request->input('created_at'));
        $status = $request->input('status');

        try {
            $transaction = Transaction::query();
            if ($max_total_selling_price) {
                $transaction->where('total_selling_price', '<=', $max_total_selling_price);
            }
            if ($min_total_selling_price) {
                $transaction->where('total_selling_price', '>=', $min_total_selling_price);
            }
            if ($pickup_date_range) {
                $transaction->whereDate('pickup_date', '>=', $pickup_date_range[0])
                    ->whereDate('pickup_date', '<=', $pickup_date_range[1]);
            }
            if ($created_at) {
                $transaction->whereDate('created_at', '>=', $created_at[0])
                    ->whereDate('created_at', '<=', $created_at[1]);
            }
            if ($status) {
                $transaction->where('status', $status);
            }
            return ResponseFormatter::success([
                'Transaction' => $transaction->paginate($limit),
            ], 'Get transaction success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to get transaction', 500);
        }
    }

    public function getTransactionDetail(Request $request): object
    {
        try {
            $transactionCheck = Transaction::where('id', $request->transaction_id)->first();
            if (!$transactionCheck) {
                return ResponseFormatter::error([
                    'message' => 'Transaction not found',
                ], 'Failed to get transaction detail', 404);
            }

            $transactionItem = TransactionItem::with('cloth.clothImage')
                ->where('transaction_id', $request->transaction_id)
                ->get();
//            if ($transactionItem->isEmpty()) {
//                return ResponseFormatter::error([
//                    'message' => 'Transaction item not found',
//                ], 'Failed to get transaction detail', 404);
//            }

            return ResponseFormatter::success([

                'transactionItem' => $transactionItem,
            ], 'Get transaction detail success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to get transaction detail', 500);
        }
    }

    function calculatePrice(int $quantity, float $weight): float {
        // Constants
        $init_price = 10000;
        $price_per_kg = 5000;
        $price_per_quantity = $quantity > 20 ? 7000 : 5000;

        // Calculate total price for all quantities and total weight
        $total_price_all_quantity = $quantity * $price_per_quantity;
        $total_weight = $weight * $price_per_kg;

        return $init_price + $total_price_all_quantity + $total_weight;
    }
}
