<?php

namespace App\Http\Controllers;

use App\Models\NhrdcMember;
use Illuminate\Http\Request;

class NhrdcMemberController extends Controller
{
    public function index()
    {
        return response()->json($this->rosterPayload());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empcode' => 'required|string|exists:employees,EMPCODE|unique:nhrdc_members,empcode',
        ]);

        $member = NhrdcMember::create([
            'empcode' => $data['empcode'],
            'sort_order' => NhrdcMember::nextSortOrder(),
        ])->load('employee');

        return response()->json([
            'id' => $member->id,
            'empcode' => $member->empcode,
            'name' => $member->employee?->name,
            'position' => $member->employee?->POSITION,
            'role' => NhrdcMember::roleFor($member->empcode),
        ], 201);
    }

    public function destroy(NhrdcMember $nhrdcMember)
    {
        $nhrdcMember->delete();

        return response()->json(['deleted' => true]);
    }

    // POST /nhrdc-members/{nhrdcMember}/move-up
    public function moveUp(NhrdcMember $nhrdcMember)
    {
        $nhrdcMember->moveUp();

        return response()->json($this->rosterPayload());
    }

    // POST /nhrdc-members/{nhrdcMember}/move-down
    public function moveDown(NhrdcMember $nhrdcMember)
    {
        $nhrdcMember->moveDown();

        return response()->json($this->rosterPayload());
    }

    private function rosterPayload()
    {
        return NhrdcMember::rosterWithRoles()->map(fn (NhrdcMember $m) => [
            'id' => $m->id,
            'empcode' => $m->empcode,
            'name' => $m->employee?->name,
            'position' => $m->employee?->POSITION,
            'role' => $m->role,
        ])->values();
    }
}
