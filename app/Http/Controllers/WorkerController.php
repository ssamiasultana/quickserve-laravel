<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class WorkerController extends Controller
{
    public function createWorker(Request $request): JsonResponse
    {


        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:workers,email',
            'phone' => 'nullable|string|max:20',
            'age' => 'required|integer|min:18',
            'image' => 'nullable|string',

            'service_type' => 'required|array',
            'service_type.*' => 'string|max:255',
            'expertise_of_service' => 'required|integer|max:255',
            'shift' => 'required|string|max:100',
            'rating' => 'nullable|numeric|min:0|max:5|between:0,5',
            'feedback' => 'nullable|string',

            'is_active' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        // if ($request->hasFile('image')) {
        //     $image = $request->file('image');
        //     $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

        //     $imagePath = $image->storeAs('public/images/workers', $imageName);


        //     $validatedData['image'] = 'storage/images/workers/' . $imageName;
        // }

        if (isset($validatedData['service_type']) && is_array($validatedData['service_type'])) {
            $validatedData['service_type'] = json_encode($validatedData['service_type']);
        }
        $worker = Worker::create($validatedData);

        return response()->json([
            'success' => true,
            'data' => $worker,
            'message' => 'Worker created successfully'
        ], 201);

    }

    public function getAllWorkers(): JsonResponse
    {
        $workers = Worker::all();

        return response()->json([
            'success' => true,
            'data' => $workers,
            'message' => 'Workers retrieved successfully'
        ]);
    }
    public function createBulkWorkers(Request $request): JsonResponse
    {
        $workersData = $request->all();

        if (!is_array($workersData)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data format. Expected array of workers.'
            ], 422);
        }

        $createdWorkers = [];
        $errors = [];

        foreach ($workersData as $index => $workerData) {
            try {
                $validator = Validator::make($workerData, [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:workers,email',
                    'phone' => 'nullable|string|max:20',
                    'age' => 'required|integer|min:18',
                    'image' => 'nullable|string',

                    'service_type' => 'required|array',
                    'service_type.*' => 'string|max:255',
                    'expertise_of_service' => 'required|integer|max:255',
                    'shift' => 'required|string|max:100',
                    'rating' => 'nullable|numeric|min:0|max:5',
                    'feedback' => 'nullable|string',

                    'is_active' => 'boolean'
                ]);

                if ($validator->fails()) {
                    $errors[$index] = $validator->errors()->toArray();
                    continue;
                }

                $validatedData = $validator->validated();

                // Convert service_type array to JSON string for database
                if (isset($validatedData['service_type']) && is_array($validatedData['service_type'])) {
                    $validatedData['service_type'] = json_encode($validatedData['service_type']);
                }

                $worker = Worker::create($validatedData);
                $createdWorkers[] = $worker;

            } catch (\Exception $e) {
                $errors[$index] = $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $createdWorkers,
            'errors' => $errors,
            'message' => 'Bulk worker creation completed. Created: ' . count($createdWorkers) . ', Errors: ' . count($errors)
        ], 201);
    }

    public function updateWorker(Request $request, $id): JsonResponse
    {
        $worker = Worker::find($id);

        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:workers,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'age' => 'sometimes|integer|min:18',
            'image' => 'nullable|string',

            'service_type' => 'sometimes|array',
            'service_type.*' => 'string|max:255',
            'expertise_of_service' => 'sometimes|integer|max:255',
            'shift' => 'sometimes|string|max:100',
            'rating' => 'nullable|numeric|min:0|max:5|between:0,5',
            'feedback' => 'nullable|string',

            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // Handle service_type array to JSON conversion
        if (isset($validatedData['service_type']) && is_array($validatedData['service_type'])) {
            $validatedData['service_type'] = json_encode($validatedData['service_type']);
        }

        $worker->update($validatedData);

        return response()->json([
            'success' => true,
            'data' => $worker,
            'message' => 'Worker updated successfully'
        ]);
    }
    public function deleteWorker($id): JsonResponse
    {
        $worker = Worker::find($id);

        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found'
            ], 404);
        }

        $worker->delete();

        return response()->json([
            'success' => true,
            'message' => 'Worker deleted successfully'
        ]);
    }
}
