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
                'weight' => $request->weight,
                'quantity' => $request->quantity,
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
                'total_selling_price' => $request->total_selling_price,
                'total_shipping_cost' => $request->total_shipping_cost,
                'shipping_date' => Carbon::parse($request->shipping_date)->format('Y-m-d H:i:s'),
                'status' => $request->status,
                'account_type' => $request->account_type,
                'account_number' => $request->account_number,
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
}
