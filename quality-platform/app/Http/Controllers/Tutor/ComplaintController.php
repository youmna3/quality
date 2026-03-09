<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StoreComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Complaint::class);

        $tutor = auth()->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');

        $complaints = Complaint::query()
            ->where('tutor_id', $tutor->id)
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Tutor/Complaints/Index', [
            'complaints' => $complaints,
        ]);
    }

    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $tutor = $request->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');

        Complaint::create([
            'tutor_id' => $tutor->id,
            'project_id' => $tutor->project_id,
            'session_date' => $request->validated('session_date'),
            'slot' => $request->validated('slot'),
            'group_code' => $request->validated('group_code'),
            'issue_type' => $request->validated('issue_type'),
            'complaint_text' => $request->validated('complaint_text'),
            'status' => 'new',
        ]);

        $issueLabel = $request->validated('issue_type') === 'student' ? 'Student issue' : 'Session issue';

        return back()->with('success', "{$issueLabel} submitted successfully.");
    }
}
