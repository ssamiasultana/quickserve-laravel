<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class ModeratorController extends Controller
{
    public function getAllModerators(): JsonResponse
    {
        $moderators = User::where('role', 'Moderator')
            ->select('id', 'name', 'email', 'phone', 'is_active', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $moderators,
            'count' => $moderators->count()
        ], 200);
    }

    public function getPaginated(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        
        $moderators = User::where('role', 'Moderator')
            ->select('id', 'name', 'email', 'phone', 'is_active', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $moderators->items(),
            'pagination' => [
                'total' => $moderators->total(),
                'per_page' => $moderators->perPage(),
                'current_page' => $moderators->currentPage(),
                'last_page' => $moderators->lastPage(),
            ]
        ], 200);
    }

    public function getSingleModerator($id): JsonResponse
    {
        $moderator = User::where('role', 'Moderator')
            ->select('id', 'name', 'email', 'phone', 'is_active', 'created_at', 'updated_at')
            ->find($id);

        if (!$moderator) {
            return response()->json([
                'success' => false,
                'message' => 'Moderator not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $moderator
        ], 200);
    }

    public function updateModerator(Request $request, $id): JsonResponse
    {
        $moderator = User::where('role', 'Moderator')->find($id);

        if (!$moderator) {
            return response()->json([
                'success' => false,
                'message' => 'Moderator not found'
            ], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $moderator->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $moderator,
            'message' => 'Moderator updated successfully'
        ]);
    }

    public function deleteModerator($id): JsonResponse
    {
        $moderator = User::where('role', 'Moderator')->find($id);

        if (!$moderator) {
            return response()->json([
                'success' => false,
                'message' => 'Moderator not found'
            ], 404);
        }

        $moderator->delete();

        return response()->json([
            'success' => true,
            'message' => 'Moderator deleted successfully'
        ]);
    }
}
