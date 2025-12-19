<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

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
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'expertise_of_service' => 'required|array|min:1',
            'expertise_of_service.*' => 'integer|min:1|max:5',
            'shift' => 'required|string|max:100',
            'feedback' => 'nullable|string',
            'is_active' => 'boolean',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // Handle user_id assignment
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

        // Check if worker already exists for this user
        $existingWorker = Worker::where('user_id', $validatedData['user_id'])->first();
        if ($existingWorker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker profile already exists for this user'
            ], 409);
        }

        // Extract service_ids before creating worker
        $serviceIds = $validatedData['service_ids'];
        unset($validatedData['service_ids']);

        // Create worker
        $worker = Worker::create($validatedData);

        // Attach services to worker
        $worker->services()->attach($serviceIds);

        // Load services relationship
        $worker->load('services');

        return response()->json([
            'success' => true,
            'data' => $worker,
            'message' => 'Worker created successfully'
        ], 201);
    }

    public function getAllWorkers(): JsonResponse
    {
        // $workers = Worker::all();
        $workers = Worker::with('services')->get();

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
            'service_ids' => 'sometimes|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'expertise_of_service' => 'sometimes|array',
            'expertise_of_service.*' => 'integer|min:0|max:5',
            'shift' => 'sometimes|string|max:100',
            'rating' => 'nullable|numeric|min:0|max:5',
            'feedback' => 'nullable|string',
            'is_active' => 'boolean',
            'address' => 'nullable|string',
        ]);
       
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $validatedData = $validator->validated();

        // Handle service_ids separately
        if (isset($validatedData['service_ids'])) {
            $serviceIds = $validatedData['service_ids'];
            unset($validatedData['service_ids']);
            
            // Sync services (replaces all existing services)
            $worker->services()->sync($serviceIds);
        }

        // Update worker data
        $worker->update($validatedData);

        // Load services relationship
        $worker->load('services');
    
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
        $worker = Worker::with('services')->find($id);    
        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $worker,
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
        
        $worker = Worker::with('services')->where('user_id', $user->id)->first();
        
        if (!$worker) {
            return response()->json([
                'success' => true,
                'exists' => false,
                'isComplete' => false,
                'message' => 'Complete your info to get your job'
            ]);
        }
        
        // Check if required fields are filled
        $requiredFields = ['name', 'phone', 'email', 'age', 'expertise_of_service', 'shift', 'is_active'];
        $isComplete = true;
        
        foreach ($requiredFields as $field) {
            if (empty($worker->$field)) {
                $isComplete = false;
                break;
            }
        }

        // Check if worker has at least one service
        if ($worker->services->isEmpty()) {
            $isComplete = false;
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
        $page = $request->input('page', 1); // Explicitly get page
        
        $workers = Worker::with('services')->paginate($perPage, ['*'], 'page', $page);
        
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
public function searchWorkers(Request $request)
    {
        $query = Worker::with('services');
        
        $searchTerm = trim($request->input('search', ''));
        
        if (!empty($searchTerm)) {
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                  ->orWhereHas('services', function($serviceQuery) use ($searchTerm) {
                      $serviceQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                  });
            });
        }
        
        // Filter by specific service name
        if ($request->has('service') && !empty(trim($request->service))) {
            $service = trim($request->service);
            $query->whereHas('services', function($serviceQuery) use ($service) {
                $serviceQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($service) . '%']);
            });
        }


        
        // Filter by shift
        if ($request->has('shift') && !empty(trim($request->shift))) {
            $query->where('shift', $request->shift);
        }

      
        
        $workers = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $workers,
            'count' => $workers->count(),
            'search_term' => $searchTerm,
            'message' => $workers->isEmpty() 
                ? 'No workers found matching your search' 
                : 'Workers retrieved successfully'
        ], 200);
    }

    public function getWorkersByService($serviceId): JsonResponse
    {
        $workers = Worker::whereHas('services', function($query) use ($serviceId) {
            $query->where('service_id', $serviceId);
        })->with('services')->get();

        return response()->json([
            'success' => true,
            'data' => $workers,
            'count' => $workers->count(),
            'message' => 'Workers retrieved successfully'
        ]);
    }
    public function createBulkWorkers(Request $request): JsonResponse
    {
        $authenticatedUser = auth()->user();
        
        if (!$authenticatedUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - User not authenticated'
            ], 401);
        }
    
        // Only admins can bulk create workers
        if (!$authenticatedUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Only admins can bulk create workers'
            ], 403);
        }
    
        // Validate the main structure
        $mainValidator = Validator::make($request->all(), [
            'workers' => 'required|array|min:1|max:100',
        ]);
    
        if ($mainValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data format',
                'errors' => $mainValidator->errors()
            ], 422);
        }
    
        $workersData = $request->input('workers');
        
        // Collect all emails for duplicate checking
        $emails = array_filter(array_column($workersData, 'email'));
        
        // Check for duplicate emails within the request
        $duplicateEmailsInRequest = array_diff_assoc($emails, array_unique($emails));
        if (!empty($duplicateEmailsInRequest)) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate emails found in request',
                'duplicate_emails' => array_values(array_unique($duplicateEmailsInRequest))
            ], 422);
        }
    
        // Check for existing emails in database
        $existingEmails = Worker::whereIn('email', $emails)->pluck('email')->toArray();
        if (!empty($existingEmails)) {
            return response()->json([
                'success' => false,
                'message' => 'Some emails already exist in database',
                'existing_emails' => $existingEmails
            ], 409);
        }
    
        // Check for existing user_ids if provided
        $userIds = array_filter(array_column($workersData, 'user_id'));
        if (!empty($userIds)) {
            $existingUserIds = Worker::whereIn('user_id', $userIds)->pluck('user_id')->toArray();
            if (!empty($existingUserIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker profiles already exist for some users',
                    'existing_user_ids' => $existingUserIds
                ], 409);
            }
        }
    
        DB::beginTransaction();
        
        try {
            $createdWorkers = [];
            $errors = [];
    
            foreach ($workersData as $index => $workerData) {
                try {
                    // Validate each worker
                    $validator = Validator::make($workerData, [
                        'user_id' => 'nullable|integer|exists:users,id',
                        'name' => 'required|string|max:255',
                        'email' => 'required|email',
                        'phone' => 'nullable|string|max:20',
                        'age' => 'required|integer|min:18',
                        'image' => 'nullable|string',
                        'service_ids' => 'required|array|min:1',
                        'service_ids.*' => 'exists:services,id',
                        'expertise_of_service' => 'required|array|min:1',
                        'expertise_of_service.*' => 'integer|min:1|max:5',
                        'shift' => 'required|string|max:100',
                        'feedback' => 'nullable|string',
                        'is_active' => 'boolean',
                        'address' => 'nullable|string',
                    ]);
    
                    if ($validator->fails()) {
                        $errors[] = [
                            'index' => $index,
                            'email' => $workerData['email'] ?? 'N/A',
                            'name' => $workerData['name'] ?? 'N/A',
                            'errors' => $validator->errors()->toArray()
                        ];
                        continue;
                    }
    
                    $validatedData = $validator->validated();
    
                    // Handle user_id assignment
                    if (isset($workerData['user_id']) && $workerData['user_id']) {
                        $validatedData['user_id'] = $workerData['user_id'];
                    } else {
                        $validatedData['user_id'] = $authenticatedUser->id;
                    }
    
                    // Extract service_ids before creating worker
                    $serviceIds = $validatedData['service_ids'];
                    unset($validatedData['service_ids']);
    
                    // Set default is_active if not provided
                    if (!isset($validatedData['is_active'])) {
                        $validatedData['is_active'] = true;
                    }
    
                    // Create worker
                    $worker = Worker::create($validatedData);
    
                    // Attach services to worker
                    $worker->services()->attach($serviceIds);
    
                    // Load services relationship
                    $worker->load('services');
    
                    $createdWorkers[] = $worker;
    
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'email' => $workerData['email'] ?? 'N/A',
                        'name' => $workerData['name'] ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                }
            }
    
            // If there are any errors, rollback everything
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Bulk creation failed due to validation errors',
                    'errors' => $errors,
                    'created_count' => 0,
                    'failed_count' => count($errors)
                ], 422);
            }
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'data' => $createdWorkers,
                'message' => 'All workers created successfully',
                'created_count' => count($createdWorkers),
                'failed_count' => 0
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during bulk creation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}