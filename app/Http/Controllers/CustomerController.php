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
}
