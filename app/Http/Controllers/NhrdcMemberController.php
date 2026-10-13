<?php

namespace App\Http\Controllers;

use App\Models\NhrdcMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NhrdcMemberController extends Controller
{
    public function index()
    {
        return response()->json($this->rosterPayload());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'empcode' => [
                'required', 'string', 'exists:employees,EMPCODE', 'unique:nhrdc_members,empcode',
                // A representative rates as another member, so they can't also be on the roster.
                Rule::unique('nhrdc_members', 'representative_empcode'),
            ],
        ], [
            'empcode.unique' => 'This employee is already on the roster or is assigned as an NHRDC representative.',
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
            'representative' => null,
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

    // PUT /nhrdc-members/{nhrdcMember}/representative
    // Standing representative: may rate nominees on this member's behalf in
    // every program until removed. The member remains the signatory.
    public function assignRepresentative(Request $request, NhrdcMember $nhrdcMember): JsonResponse
    {
        $request->validate([
            'empcode' => [
                'required', 'string', 'exists:employees,EMPCODE',
                // Must have an account to log in and rate.
                'exists:users,empcode',
                Rule::notIn([$nhrdcMember->empcode]),
                Rule::unique('nhrdc_members', 'empcode'),
                Rule::unique('nhrdc_members', 'representative_empcode')->ignore($nhrdcMember->id),
            ],
        ], [
            'empcode.exists' => 'This employee has no user account yet, so they cannot log in to rate.',
            'empcode.not_in' => 'An NHRDC member cannot be their own representative.',
            'empcode.unique' => 'This employee is an NHRDC member or already represents another NHRDC member.',
        ]);

        $nhrdcMember->update(['representative_empcode' => $request->input('empcode')]);

        return response()->json($this->rosterPayload());
    }

    // DELETE /nhrdc-members/{nhrdcMember}/representative
    public function removeRepresentative(NhrdcMember $nhrdcMember): JsonResponse
    {
        $nhrdcMember->update(['representative_empcode' => null]);

        return response()->json($this->rosterPayload());
    }

    private function rosterPayload()
    {
        return NhrdcMember::rosterWithRoles()->load('representative')->map(fn (NhrdcMember $m) => [
            'id' => $m->id,
            'empcode' => $m->empcode,
            'name' => $m->employee?->name,
            'position' => $m->employee?->POSITION,
            'role' => $m->role,
            'representative' => $m->representative_empcode ? [
                'empcode' => $m->representative_empcode,
                'name' => $m->representative?->name,
                'position' => $m->representative?->POSITION,
            ] : null,
        ])->values();
    }
}
