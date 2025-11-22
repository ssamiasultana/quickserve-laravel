<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Services;
use Illuminate\Support\Facades\Validator;



class ServiceController extends Controller
{
    //
    public function createServices(Request $request):JsonResponse{

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $validatedData = $validator->validated();
        $services = Services::create($validatedData);

        return response()->json([
            'success' => true,
            'data' => $services,
            'message' => 'Service created successfully'
        ], 201);

    }

    public function getServices(): JsonResponse
    {
        $service = Services::all();

        return response()->json([
            'success' => true,
            'data' => $service,
            'message' => 'Srvice retrieved successfully'
        ]);
    }

    public function updateServices(Request $request, $id):JsonResponse{
        $service = Services::find($id);
        if(!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $service->update($validatedData);

        return response()->json([
            'success' => true,
            'data' => $service,
            'message' => 'Service updated successfully'
        ]);

    }
    public function deleteServices($id): JsonResponse
    {
        $service = Services::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    }
}
