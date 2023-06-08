<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCloth;
use App\Models\Cloth;
use App\Models\ClothImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Google\Cloud\Storage\StorageClient;

class ClothController extends Controller
{

    public function createUserCloth(Request $request): object
    {
        $user = Auth::user();
        try {
            $userCloth = UserCloth::create([
                'user_id' => $user->id,
                'amount_of_clothes' => $request->amount_of_clothes,
            ]);
            return ResponseFormatter::success([
                'UserClothes' => $userCloth,
            ], 'Create user cloth success');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to create user cloth', 500);
        }
    }

    public function createClothImage(Request $request): object
    {
        $user = Auth::user();
        try{

            $userCloth = UserCloth::where('id', $request->user_cloth_id)->first();
            if (!$userCloth) {
                return ResponseFormatter::error([
                    'message' => 'User cloth not found',
                ], 'Failed to create cloth image', 404);
            }

            // Upload image to bucket and run model
            $image = $this->uploadImage($request->file('original_image'));

            // get response json from $image
            $response = json_decode($image->getContent());

            $clothImage = ClothImage::create([
                'user_cloth_id' => $request->user_cloth_id,
                'original_image' => $response->data->file_name,
                'defects_proof' => "OK",
                'fabric_status' => $response->data->ore->prediction,
            ]);

            return ResponseFormatter::success([
                "clothImage" => $clothImage,
                'imageUrl' => $response->data->google_storage_url,
                "imageHashName" => $response->data->file_name,
            ], 'Success upload cloth');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to upload cloth', 500);
        }
    }

    public function createCloth(Request $request): object
    {
        $user = Auth::user();
        try {

            $clothImage = ClothImage::where('id', $request->cloth_image_id)->first();
            if (!$clothImage) {
                return ResponseFormatter::error([
                    'message' => 'Cloth image not found',
                ], 'Failed to create cloth', 404);
            }

            $cloth = Cloth::create([
                'cloth_image_id' => $request->cloth_image_id,
                'type' => $request->type,
                'description' => $request->description,
                'is_ready' => false,
            ]);

            return ResponseFormatter::success([
                'cloth' => $cloth,
            ], 'Success create cloth');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 'Failed to create cloth', 500);
        }
    }

    public function uploadImage(UploadedFile $file): object
    {
        // Initialize Google Cloud Storage
        $googleConfigFile = file_get_contents(config_path('../certs/bucket.json'));
        $storage = new StorageClient([
            'keyFile' => json_decode($googleConfigFile, true)
        ]);

        // Get the bucket
        $storageBucketName = config('googlecloud.storage_bucket');
        $bucket = $storage->bucket($storageBucketName);

        // Upload the file
        $newFolderName = 'users-original-cloths';
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
            ], 'Failed to upload the file', 500);
        }

        // Call the classifyImage function
        $ore = $this->classifyImage($storageBucketName, $googleCloudStoragePath);
        $getOre = json_decode($ore->body(), true);

        return ResponseFormatter::success([
            "url" => url($googleCloudStoragePath),
            "file_name" => $file->hashName(),
            "ore" => $getOre,
            "google_storage_url" => 'https://storage.googleapis.com/'.$storageBucketName.'/'.$googleCloudStoragePath
        ], 'File uploaded successfully');
    }

    public function classifyImage($bucketName, $objectName): object
    {
        $bucketConfig = json_decode(file_get_contents(config_path('../certs/bucket.json')), true);
        $storage = new StorageClient([
            'keyFile' => $bucketConfig,
        ]);

        $bucket = $storage->bucket($bucketName);
        $object = $bucket->object($objectName);
        $stream = $object->downloadAsStream();
        $imageData = $stream->__toString(); // Convert the stream to a string

        // Prepare the image data
        $base64ImageData = base64_encode($imageData);

        return Http::withHeaders([
            'x-secret-key' => env('SECRET_KEY'),
        ])->post('https://asia-southeast1-recloth.cloudfunctions.net/preprocessing-fabric', [
            'image' => $base64ImageData,
        ]);
    }
}
