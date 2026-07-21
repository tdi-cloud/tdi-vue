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
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:organizing_sponsors,name',
            'full_name' => 'nullable|string|max:255',
        ]);

        $sponsor = OrganizingSponsor::create($data);

        return response()->json($sponsor, 201);
    }

    public function update(Request $request, OrganizingSponsor $organizingSponsor)
    {
        $data = $request->validate([
            'full_name' => 'nullable|string|max:255',
        ]);

        $organizingSponsor->update($data);

        return response()->json($organizingSponsor);
    }

    public function destroy(OrganizingSponsor $organizingSponsor)
    {
        $organizingSponsor->delete();

        return response()->json(['deleted' => true]);
    }
}
