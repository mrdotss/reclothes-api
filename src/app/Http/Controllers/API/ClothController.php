<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\UserCloth;
use App\Models\Cloth;
use App\Models\ClothImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
                'original_image' => $response->data->fabric_file_name,
                'defects_proof' => $response->data->defects_google_storage_url,
                'fabric_status' => $response->data->fabric_result->prediction,
            ]);

            return ResponseFormatter::success([
                "clothImage" => $clothImage,
                "fabricImageUrl" => $response->data->fabric_google_storage_url,
                "defectsFileName" => $response->data->defects_file_name,
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
        $googleConfigFile = base64_decode(env('GOOGLE_CREDENTIALS'));
        $keyFileArray = json_decode($googleConfigFile, true);
        $storage = new StorageClient([
            'keyFile' => $keyFileArray,
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
            ], 'Failed to upload the fabric file', 500);
        }

        // Call the model
        $fabricClassify = $this->fabricClassification($storageBucketName, $googleCloudStoragePath);
        $defectsPrediction = $this->defectsPrediction($storageBucketName, $googleCloudStoragePath);

        // Get the response
        $getFabricClassify = json_decode($fabricClassify->body(), true);
        $getDefectsPrediction = json_decode($defectsPrediction->body(), true);

        $base64_string  = $getDefectsPrediction["image_result"];

        // Decode the base64 string
        $image_data = base64_decode($base64_string);

        $newFolderName = 'users-defects-cloths';

        // Generate a random filename for the image
        $newFileName = hash('sha256', $image_data . time()) . '.jpg';
        $defectsGoogleCloudStoragePath = $newFolderName.'/'.$newFileName;
        $bucket->upload(
            $image_data,
            [
                'name' => $defectsGoogleCloudStoragePath,
                'metadata' => [
                    'cacheControl' => 'public, max-age=86400',
                ],
            ]
        );

        if (!$bucket->object($defectsGoogleCloudStoragePath)->exists()) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
            ], 'Failed to upload the defects file', 500);
        }

        return ResponseFormatter::success([
            "fabric_url" => url($googleCloudStoragePath),
            "fabric_file_name" => $file->hashName(),
            "fabric_result" => $getFabricClassify,
            "fabric_google_storage_url" => 'https://storage.googleapis.com/'.$storageBucketName.'/'.$googleCloudStoragePath,
            "defects_url" => url($defectsGoogleCloudStoragePath),
            "defects_file_name" => $newFileName,
            "defects_google_storage_url" => 'https://storage.googleapis.com/'.$storageBucketName.'/'.$defectsGoogleCloudStoragePath,
        ], 'File uploaded successfully');
    }

    public function fabricClassification($bucketName, $objectName): object
    {
        $endpoint = 'https://asia-southeast1-recloth.cloudfunctions.net/preprocessing-fabric';
        return $this->makeImageRequest($bucketName, $objectName, $endpoint);
    }

    public function defectsPrediction($bucketName, $objectName): object
    {
        $endpoint = 'https://asia-southeast1-recloth.cloudfunctions.net/pre-prost-processing-defects';
        return $this->makeImageRequest($bucketName, $objectName, $endpoint);
    }

    private function makeImageRequest($bucketName, $objectName, $endpoint): object
    {
        $googleConfigFile = base64_decode(env('GOOGLE_CREDENTIALS'));
        $keyFileArray = json_decode($googleConfigFile, true);
        $storage = new StorageClient([
            'keyFile' => $keyFileArray,
        ]);

        $bucket = $storage->bucket($bucketName);
        $object = $bucket->object($objectName);
        $stream = $object->downloadAsStream();
        $imageData = $stream->__toString(); // Convert the stream to a string

        // Prepare the image data
        $base64ImageData = base64_encode($imageData);

        return Http::withHeaders([
            'x-secret-key' => env('SECRET_KEY'),
        ])->post($endpoint, [
            'image' => $base64ImageData,
        ]);
    }
}
