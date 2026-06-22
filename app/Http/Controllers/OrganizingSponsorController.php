<?php

namespace App\Http\Controllers;

use App\Models\OrganizingSponsor;
use Illuminate\Http\Request;

class OrganizingSponsorController extends Controller
{
    public function index()
    {
        return response()->json(
            OrganizingSponsor::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:organizing_sponsors,name',
        ]);

        $sponsor = OrganizingSponsor::create(['name' => $request->name]);

        return response()->json($sponsor, 201);
    }

    public function destroy(OrganizingSponsor $organizingSponsor)
    {
        $organizingSponsor->delete();

        return response()->json(['deleted' => true]);
    }
}