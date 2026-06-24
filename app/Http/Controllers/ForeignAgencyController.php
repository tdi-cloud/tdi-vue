<?php

namespace App\Http\Controllers;

use App\Models\ForeignAgency;
use Illuminate\Http\Request;

class ForeignAgencyController extends Controller
{
    public function index()
    {
        return response()->json(
            ForeignAgency::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:foreign_agencies,name',
        ]);

        ForeignAgency::create(['name' => $request->name]);

        return back()->with('success', 'Agency added.');
    }

    public function destroy(ForeignAgency $foreignAgency)
    {
        $foreignAgency->delete();

        return back()->with('success', 'Agency removed.');
    }
}