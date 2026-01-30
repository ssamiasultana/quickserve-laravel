<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class CustomerController extends Controller
{
    //
    public function getAllCustomers(): JsonResponse
    {
        $customers = User::where('role', 'Customer')
            ->select('id', 'name', 'email', 'phone', 'created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
            'count' => $customers->count()
        ], 200);
    }

    public function getPaginated(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        
        $customers = User::where('role', 'Customer')
            ->select('id', 'name', 'email', 'phone', 'created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'pagination' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ]
        ], 200);
    }

    public function updateCustomer(Request $request, $id): JsonResponse
    {
        $customer = User::where('role', 'Customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $customer,
            'message' => 'Customer updated successfully'
        ]);
    }

    public function deleteCustomer($id): JsonResponse
    {
        $customer = User::where('role', 'Customer')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }
}
