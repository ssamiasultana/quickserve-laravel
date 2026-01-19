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
use App\Helpers\NIDValidator;

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
            'nid' => 'required|string|max:20',
            'nid_front_image' => 'nullable|string',
            'nid_back_image' => 'nullable|string',
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

        // Validate NID format
        if (isset($validatedData['nid'])) {
            $nidValidation = NIDValidator::validate($validatedData['nid']);
            if (!$nidValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'NID validation failed',
                    'errors' => ['nid' => $nidValidation['message']]
                ], 422);
            }

            // Check NID uniqueness
            if (!NIDValidator::isUnique($validatedData['nid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'NID is already registered',
                    'errors' => ['nid' => 'This NID number is already registered to another worker']
                ], 422);
            }

            // Validate age consistency if both age and NID are provided
            if (isset($validatedData['age'])) {
                // Log for debugging
                \Log::info('Age validation (update)', [
                    // 'worker_id' => $id,
                    'nid' => $validatedData['nid'],
                    'age' => $validatedData['age'],
                    'raw_age' => $request->input('age')
                ]);
                
                $ageValidation = NIDValidator::validateAgeConsistency(
                    $validatedData['nid'],
                    $validatedData['age'],
                    3 // 3 year tolerance
                );
                if (!$ageValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Age validation failed',
                        'errors' => ['age' => $ageValidation['message']]
                    ], 422);
                }
            }
        }

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
   

    /**
     * Get the current authenticated worker's profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get worker by user_id
        $worker = Worker::with('services')->where('user_id', $user->id)->first();

        // Fallback: try to find by email
        if (!$worker) {
            $worker = Worker::with('services')->where('email', $user->email)->first();
            
            if ($worker && !$worker->user_id) {
                $worker->user_id = $user->id;
                $worker->save();
            }
        }

        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker profile not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $worker,
            'message' => 'Profile retrieved successfully'
        ]);
    }

    /**
     * Update the current authenticated worker's profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get worker by user_id
        $worker = Worker::with('services')->where('user_id', $user->id)->first();

        // Fallback: try to find by email
        if (!$worker) {
            $worker = Worker::with('services')->where('email', $user->email)->first();
            
            if ($worker && !$worker->user_id) {
                $worker->user_id = $user->id;
                $worker->save();
            }
        }

        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker profile not found'
            ], 404);
        }

        // Use the existing update logic but with the found worker
        return $this->updateWorkerData($request, $worker);
    }

    /**
     * Update worker data (shared logic for updateWorker and updateProfile)
     */
    private function updateWorkerData(Request $request, Worker $worker): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:workers,email,' . $worker->id,
            'phone' => 'nullable|string|max:20',
            'age' => 'sometimes|integer|min:18',
            'image' => 'nullable|string',
            'nid' => 'sometimes|string|max:20',
            'nid_front_image' => 'nullable|string',
            'nid_back_image' => 'nullable|string',
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

        // Validate NID format if provided
        if (isset($validatedData['nid'])) {
            $nidValidation = NIDValidator::validate($validatedData['nid']);
            if (!$nidValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'NID validation failed',
                    'errors' => ['nid' => $nidValidation['message']]
                ], 422);
            }

            // Check NID uniqueness (exclude current worker)
            if (!NIDValidator::isUnique($validatedData['nid'], $worker->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'NID is already registered',
                    'errors' => ['nid' => 'This NID number is already registered to another worker']
                ], 422);
            }

            // Validate age consistency if both age and NID are provided
            if (isset($validatedData['age'])) {
                // Log for debugging
                \Log::info('Age validation (update)', [
                    'worker_id' => $worker->id,
                    'nid' => $validatedData['nid'],
                    'age' => $validatedData['age'],
                    'raw_age' => $request->input('age')
                ]);
                
                $ageValidation = NIDValidator::validateAgeConsistency(
                    $validatedData['nid'],
                    $validatedData['age'],
                    3 // 3 year tolerance
                );
                if (!$ageValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Age validation failed',
                        'errors' => ['age' => $ageValidation['message']]
                    ], 422);
                }
            }
        }

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

    public function updateWorker(Request $request, $id): JsonResponse
    {
        $worker = Worker::find($id);
    
        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found'
            ], 404);
        }

        // Check authorization: Only admin/moderator or the worker themselves can update
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Allow admin/moderator to update any worker, or worker to update their own profile
        $isAdminOrModerator = in_array($user->role, ['Admin', 'Moderator']);
        $isOwnProfile = $worker->user_id == $user->id;

        if (!$isAdminOrModerator && !$isOwnProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - You can only update your own profile'
            ], 403);
        }
        
        return $this->updateWorkerData($request, $worker);
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
            // Check if worker exists with this user's email (in case user_id wasn't set)
            $worker = Worker::with('services')->where('email', $user->email)->first();
            
            if ($worker && !$worker->user_id) {
                // Update the worker with the user_id if it's missing
                $worker->user_id = $user->id;
                $worker->save();
            }
        }
        
        if (!$worker) {
            return response()->json([
                'success' => true,
                'exists' => false,
                'isComplete' => false,
                'message' => 'Complete your info to get your job',
                'debug' => [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'note' => 'No worker found with matching user_id or email'
                ]
            ]);
        }
        
        // Check if required fields are filled
        $requiredFields = ['name', 'phone', 'email', 'age', 'expertise_of_service', 'shift', 'is_active'];
        $isComplete = true;
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            // Handle special cases
            if ($field === 'is_active') {
                // is_active can be 0 or false, which is valid, but null is not
                if (is_null($worker->$field)) {
                    $isComplete = false;
                    $missingFields[] = $field;
                }
            } elseif ($field === 'expertise_of_service') {
                // Check if it's an array and not empty
                if (empty($worker->$field) || (is_array($worker->$field) && count($worker->$field) === 0)) {
                    $isComplete = false;
                    $missingFields[] = $field;
                }
            } else {
                // For other fields, check if empty or null
                $value = $worker->$field;
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $isComplete = false;
                    $missingFields[] = $field;
                }
            }
        }

        // Check if worker has at least one service
        if ($worker->services->isEmpty()) {
            $isComplete = false;
            if (!in_array('services', $missingFields)) {
                $missingFields[] = 'services';
            }
        }
        
        return response()->json([
            'success' => true,
            'exists' => true,
            'isComplete' => $isComplete,
            'message' => $isComplete ? 'Profile is complete' : 'Complete your info to get your job',
            'missingFields' => $missingFields, // Add this for debugging
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
    // public function createBulkWorkers(Request $request): JsonResponse
    // {
    //     $authenticatedUser = auth()->user();
        
    //     if (!$authenticatedUser) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized - User not authenticated'
    //         ], 401);
    //     }
    
    //     // Only admins can bulk create workers
    //     if (!$authenticatedUser->isAdmin()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized - Only admins can bulk create workers'
    //         ], 403);
    //     }
    
    //     // Validate the main structure
    //     $mainValidator = Validator::make($request->all(), [
    //         'workers' => 'required|array|min:1|max:100',
    //     ]);
    
    //     if ($mainValidator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid data format',
    //             'errors' => $mainValidator->errors()
    //         ], 422);
    //     }
    
    //     $workersData = $request->input('workers');
        
    //     // Collect all emails for duplicate checking
    //     $emails = array_filter(array_column($workersData, 'email'));
        
    //     // Check for duplicate emails within the request
    //     $duplicateEmailsInRequest = array_diff_assoc($emails, array_unique($emails));
    //     if (!empty($duplicateEmailsInRequest)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Duplicate emails found in request',
    //             'duplicate_emails' => array_values(array_unique($duplicateEmailsInRequest))
    //         ], 422);
    //     }
    
    //     // Check for existing emails in database
    //     $existingEmails = Worker::whereIn('email', $emails)->pluck('email')->toArray();
    //     if (!empty($existingEmails)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Some emails already exist in database',
    //             'existing_emails' => $existingEmails
    //         ], 409);
    //     }
    
    //     // Check for existing user_ids if provided
    //     $userIds = array_filter(array_column($workersData, 'user_id'));
    //     if (!empty($userIds)) {
    //         $existingUserIds = Worker::whereIn('user_id', $userIds)->pluck('user_id')->toArray();
    //         if (!empty($existingUserIds)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Worker profiles already exist for some users',
    //                 'existing_user_ids' => $existingUserIds
    //             ], 409);
    //         }
    //     }
    
    //     DB::beginTransaction();
        
    //     try {
    //         $createdWorkers = [];
    //         $errors = [];
    
    //         foreach ($workersData as $index => $workerData) {
    //             try {
    //                 // Validate each worker
    //                 $validator = Validator::make($workerData, [
    //                     'user_id' => 'nullable|integer|exists:users,id',
    //                     'name' => 'required|string|max:255',
    //                     'email' => 'required|email',
    //                     'phone' => 'nullable|string|max:20',
    //                     'age' => 'required|integer|min:18',
    //                     'image' => 'nullable|string',
    //                     'service_ids' => 'required|array|min:1',
    //                     'service_ids.*' => 'exists:services,id',
    //                     'expertise_of_service' => 'required|array|min:1',
    //                     'expertise_of_service.*' => 'integer|min:1|max:5',
    //                     'shift' => 'required|string|max:100',
    //                     'feedback' => 'nullable|string',
    //                     'is_active' => 'boolean',
    //                     'address' => 'nullable|string',
    //                 ]);
    
    //                 if ($validator->fails()) {
    //                     $errors[] = [
    //                         'index' => $index,
    //                         'email' => $workerData['email'] ?? 'N/A',
    //                         'name' => $workerData['name'] ?? 'N/A',
    //                         'errors' => $validator->errors()->toArray()
    //                     ];
    //                     continue;
    //                 }
    
    //                 $validatedData = $validator->validated();
    
    //                 // Handle user_id assignment
    //                 if (isset($workerData['user_id']) && $workerData['user_id']) {
    //                     $validatedData['user_id'] = $workerData['user_id'];
    //                 } else {
    //                     $validatedData['user_id'] = $authenticatedUser->id;
    //                 }
    
    //                 // Extract service_ids before creating worker
    //                 $serviceIds = $validatedData['service_ids'];
    //                 unset($validatedData['service_ids']);
    
    //                 // Set default is_active if not provided
    //                 if (!isset($validatedData['is_active'])) {
    //                     $validatedData['is_active'] = true;
    //                 }
    
    //                 // Create worker
    //                 $worker = Worker::create($validatedData);
    
    //                 // Attach services to worker
    //                 $worker->services()->attach($serviceIds);
    
    //                 // Load services relationship
    //                 $worker->load('services');
    
    //                 $createdWorkers[] = $worker;
    
    //             } catch (\Exception $e) {
    //                 $errors[] = [
    //                     'index' => $index,
    //                     'email' => $workerData['email'] ?? 'N/A',
    //                     'name' => $workerData['name'] ?? 'N/A',
    //                     'error' => $e->getMessage()
    //                 ];
    //             }
    //         }
    
    //         // If there are any errors, rollback everything
    //         if (!empty($errors)) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Bulk creation failed due to validation errors',
    //                 'errors' => $errors,
    //                 'created_count' => 0,
    //                 'failed_count' => count($errors)
    //             ], 422);
    //         }
    
    //         DB::commit();
    
    //         return response()->json([
    //             'success' => true,
    //             'data' => $createdWorkers,
    //             'message' => 'All workers created successfully',
    //             'created_count' => count($createdWorkers),
    //             'failed_count' => 0
    //         ], 201);
    
    //     } catch (\Exception $e) {
    //         DB::rollBack();
            
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred during bulk creation',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function verifyNID(Request $request, $id): JsonResponse
    {
        $authenticatedUser = auth()->user();
        
        if (!$authenticatedUser || !$authenticatedUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Only admins can verify NID'
            ], 403);
        }
        
        $worker = Worker::find($id);
        
        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'Worker not found'
            ], 404);
        }
        
        if (!$worker->nid) {
            return response()->json([
                'success' => false,
                'message' => 'No NID provided for this worker'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $worker->nid_verified = $request->verified;
        $worker->nid_verified_at = $request->verified ? now() : null;
        // Store notes in a separate field if you add it, or in feedback temporarily
        // For now, we'll add it as a JSON in a new migration if needed
        $worker->save();
        
        // Reload with relationships
        $worker->load('services');
        
        return response()->json([
            'success' => true,
            'data' => $worker,
            'message' => $request->verified 
                ? 'NID verified successfully' 
                : 'NID verification revoked'
        ]);
    }
    
    public function checkNIDAvailability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nid' => 'required|string|min:10|max:17',
            'exclude_worker_id' => 'nullable|integer'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $nid = $request->nid;
        $excludeId = $request->exclude_worker_id;
        
        // Validate NID format
        $nidValidation = NIDValidator::validate($nid);
        if (!$nidValidation['valid']) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => $nidValidation['message']
            ], 422);
        }
        
        // Check uniqueness
        $isUnique = NIDValidator::isUnique($nid, $excludeId);
        
        return response()->json([
            'success' => true,
            'available' => $isUnique,
            'message' => $isUnique 
                ? 'NID is available' 
                : 'NID is already registered'
        ]);
    }
}