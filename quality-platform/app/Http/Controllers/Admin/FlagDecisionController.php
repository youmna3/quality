<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFlagObjectionRequest;
use App\Http\Requests\Admin\UpdateFlagStatusRequest;
use App\Models\Flag;
use Illuminate\Http\RedirectResponse;

class FlagDecisionController extends Controller
{
    public function update(UpdateFlagStatusRequest $request, Flag $flag): RedirectResponse
    {
        $flag->update([
            'status' => $request->validated('status'),
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Flag status updated successfully.');
    }

    public function updateObjection(UpdateFlagObjectionRequest $request, Flag $flag): RedirectResponse
    {
        $payload = $request->validated();

        $flag->update([
            'objection_status' => $payload['objection_status'],
            'objection_response' => $payload['objection_response'] ?? null,
            'objection_reviewed_by' => $request->user()->id,
            'objection_reviewed_at' => now(),
        ]);

        return back()->with('success', 'Flag objection updated successfully.');
    }
}
