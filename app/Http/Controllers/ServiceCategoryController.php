<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ServiceSubcategory;
use Illuminate\Support\Facades\Validator;

class ServiceCategoryController extends Controller
{
    public function createServiceCategory(Request $request){
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string|max:255',
            'base_price' => 'required|numeric|min:0',
            'unit_type' => 'required|in:fixed,hourly',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $subcategory = ServiceSubcategory::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Service subcategory created successfully',
            'data' => $subcategory->load('category')
        ], 201);
    }

    public function getServicecategory()
    {
        $subcategories = ServiceSubcategory::with('category')->get();
        
        return response()->json([
            'success' => true,
            'data' => $subcategories
        ], 200);
    }

}
