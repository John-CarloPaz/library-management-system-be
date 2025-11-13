<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        //
    }
    public function createBranch(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'details' => 'required|string|max:255',
            'public_ip' => 'required|ip',
        ]);
        Branch::create($validatedData);
        return response()->json(['message' => 'Branch created successfully'], 201);
    }
}
