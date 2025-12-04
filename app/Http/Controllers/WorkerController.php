<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class WorkerController extends Controller
{
    public function createWorker(Request $request): JsonResponse
    {

        $authenticatedUser = auth()->user(); 
        if (!$authenticatedUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User not authenticated'
            ], 401);
        }
    

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',

            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:workers,email',
            'phone' => 'nullable|string|max:20',
            'age' => 'required|integer|min:18',
            'image' => 'nullable|string',

            'service_type' => 'required|array',
            'service_type.*' => 'string|max:255',
            'expertise_of_service' => 'required|array',
            'expertise_of_service.*' => 'integer|min:1|max:5',
            'shift' => 'required|string|max:100',

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

        if($request->has('user_id') && $request->user_id){
            if(!$authenticatedUser->isAdmin()) { 
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Only admins can assign worker profiles to other users'
                ], 403);
            }
            $validatedData['user_id'] = $request->user_id;
        } else {
            $validatedData['user_id'] = $authenticatedUser->id;
        }
        $existingWorker = Worker::where('user_id', $validatedData['user_id'])->first();
    if ($existingWorker) {
        return response()->json([
            'success' => false,
            'message' => 'Worker profile already exists for this user'
        ], 409);
        }

        if (isset($validatedData['service_type']) && is_array($validatedData['service_type'])) {
            $validatedData['service_type'] = json_encode($validatedData['service_type']);
        }

        if (isset($validatedData['service_ratings']) && is_array($validatedData['service_ratings'])) {

            $serviceTypes = json_decode($validatedData['service_type'], true);
            foreach ($serviceTypes as $service) {
                if (!isset($validatedData['service_ratings'][$service])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please provide ratings for all selected services',
                        'errors' => ['service_ratings' => ["Rating missing for service: $service"]]
                    ], 422);
                }
            }
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
            'expertise_of_service' => 'sometimes|array',
            'expertise_of_service.*' => 'integer|min:0|max:5', // min:1 থেকে min:0 করুন
            'shift' => 'sometimes|string|max:100',
            'rating' => 'nullable|numeric|min:0|max:5', // rating field যোগ করুন
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
    
        // Handle expertise_of_service array to JSON conversion - এই লাইন uncomment করুন
        if (isset($validatedData['expertise_of_service']) && is_array($validatedData['expertise_of_service'])) {
            $validatedData['expertise_of_service'] = json_encode($validatedData['expertise_of_service']);
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
    
    public function getSingleWorker(Request $request,$id):JsonResponse{
        $worker = Worker::find($id);
    
        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found'
            ], 404);
        }
        $workerData = $worker->toArray();
        if (isset($workerData['service_type']) && is_string($workerData['service_type'])) {
            try {
                $workerData['service_type'] = json_decode($workerData['service_type'], true);
            } catch (\Exception $e) {
                
                $workerData['service_type'] = [];
            }
        }

      
        if (isset($workerData['expertise_of_service']) && is_string($workerData['expertise_of_service'])) {
            try {
                $workerData['expertise_of_service'] = json_decode($workerData['expertise_of_service'], true);
            } catch (\Exception $e) {
                // If JSON decode fails, keep as is or set to empty array
                $workerData['expertise_of_service'] = [];
            }
        }

       
        if (!isset($workerData['service_type']) || !is_array($workerData['service_type'])) {
            $workerData['service_type'] = [];
        }

        if (!isset($workerData['expertise_of_service']) || !is_array($workerData['expertise_of_service'])) {
            $workerData['expertise_of_service'] = [];
        }

        return response()->json([
            'success' => true,
            'data' => $workerData,
            'message' => 'Worker retrieved successfully'
        ]);
    
    }
    public function checkProfile(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json([
                'success' => false,
                'exists' => false,
                'isComplete' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $worker = Worker::where('user_id', $user->id)->first();
        
        if (!$worker) {
            return response()->json([
                'success' => true,
                'exists' => false,
                'isComplete' => false,
                'message' => 'Complete your info to get your job'
            ]);
        }
        
        // Check if required fields are filled
        $requiredFields = ['name', 'phone', 'email', 'age', 'service_type', 'expertise_of_service', 'shift', 'is_active'];
        $isComplete = true;
        
        foreach ($requiredFields as $field) {
            if (empty($worker->$field)) {
                $isComplete = false;
                break;
            }
        }
        
        return response()->json([
            'success' => true,
            'exists' => true,
            'isComplete' => $isComplete,
            'message' => $isComplete ? 'Profile is complete' : 'Complete your info to get your job',
            'worker' => $worker
        ]);
}

public function getPaginated(Request $request)
{
    $perPage = $request->input('per_page', 10);
    
    $workers = Worker::paginate($perPage);
    return response()->json([
        'success' => true,
        'data' => $workers->items(),
        'pagination' => [
            'total' => $workers->total(),
            'per_page' => $workers->perPage(),
            'current_page' => $workers->currentPage(),
            'last_page' => $workers->lastPage(),
            'from' => $workers->firstItem(),
            'to' => $workers->lastItem(),
        ],
        'message' => 'Workers retrieved successfully'
    ], 200);
}


}