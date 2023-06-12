<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
Use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function createPayment(Request $request): object
    {
        try {
            $transaction = Transaction::where('id', $request->transaction_id)->first();
            if (!$transaction) {
                return ResponseFormatter::error([
                    'message' => 'Transaction not found',
                ], 'Create payment failed', 404);
            }

            // Upload image to bucket and run model
            $payment_proof = $this->uploadImage($request->file('payment_proof'));

            // get response json from $image
            $response = json_decode($payment_proof->getContent());

            $payment = Payment::create([
                'transaction_id' => $transaction->id,
                'payment_method' => $transaction->account_type,
                'payment_amount' => $transaction->total_selling_price,
                'payment_proof' => $response->data->file_name,
                'payment_date' => now(),
            ]);
            return ResponseFormatter::success([
                'Payment' => $payment,
            ], 'Create payment success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to create payment', 500);
        }
    }

    public function uploadImage(UploadedFile $file): object
    {
        // Initialize Google Cloud Storage
        $googleConfigFile = base64_decode(env('GOOGLE_CREDENTIALS'));
        $keyFileArray = json_decode($googleConfigFile, true);
        $storage = new StorageClient([
            'keyFile' => $keyFileArray,
        ]);

        // Get the bucket
        $storageBucketName = config('googlecloud.storage_bucket');
        $bucket = $storage->bucket($storageBucketName);

        // Upload the file
        $newFolderName = 'users-payments';
        $googleCloudStoragePath = $newFolderName.'/'.$file->hashName();
        $bucket->upload(
            file_get_contents($file->getRealPath()),
            [
                'name' => $googleCloudStoragePath
            ]
        );

        if (!$bucket->object($googleCloudStoragePath)->exists()) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
            ], 'Failed to upload the proof file', 500);
        }

        return ResponseFormatter::success([
            "file_name" => $file->hashName(),
        ], 'File uploaded successfully');
    }

    public function getPayments(Request $request): object
    {
        $user = Auth::user();
        try {
            $userId = $user->id;
            $payments = Payment::whereHas('transaction', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->get();

            if (!$payments) {
                return ResponseFormatter::error([
                    'message' => 'Payment not found',
                ], 'Get payments failed', 404);
            }
            return ResponseFormatter::success([
                'Payments' => $payments,
            ], 'Get payments success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to get payments', 500);
        }
    }
}
