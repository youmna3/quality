<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlagController extends Controller
{
    public function index(Request $request): Response
    {
        $tutor = $request->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');

        $flags = Flag::query()
            ->with('review:id,reviewer_name,session_date,slot,group_code,recorded_link')
            ->where('tutor_id', $tutor->id)
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Flag $flag) {
                $objectionDeadlineAt = $flag->created_at?->copy()->addDays(2);
                $objectionClosed = $flag->status === 'removed'
                    || ($objectionDeadlineAt ? now()->gt($objectionDeadlineAt) : false);

                return [
                    'id' => $flag->id,
                    'color' => $flag->color,
                    'subcategory' => $flag->subcategory,
                    'reason' => $flag->reason,
                    'duration_text' => $flag->duration_text,
                    'status' => $flag->status,
                    'objection_text' => $flag->objection_text,
                    'objection_status' => $flag->objection_status,
                    'objection_response' => $flag->objection_response,
                    'objection_submitted_at' => $flag->objection_submitted_at?->toDateTimeString(),
                    'objection_reviewed_at' => $flag->objection_reviewed_at?->toDateTimeString(),
                    'objection_deadline_at' => $objectionDeadlineAt?->toDateTimeString(),
                    'objection_closed' => $objectionClosed,
                    'screenshot_url' => $flag->screenshot_path ? asset('storage/'.$flag->screenshot_path) : null,
                    'created_at' => $flag->created_at?->toDateTimeString(),
                    'review' => $flag->review
                        ? [
                            'session_date' => $flag->review->session_date?->toDateString(),
                            'slot' => $flag->review->slot,
                            'group_code' => $flag->review->group_code,
                            'recorded_link' => $flag->review->recorded_link,
                        ]
                        : null,
                ];
            });

        return Inertia::render('Tutor/Flags/Index', [
            'flags' => $flags,
        ]);
    }

    public function submitObjection(Request $request, Flag $flag): RedirectResponse
    {
        $tutor = $request->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');
        abort_if((int) $flag->tutor_id !== (int) $tutor->id, 403, 'You can only object to your own flags.');

        $payload = $request->validate([
            'objection_text' => ['required', 'string', 'max:2000'],
        ]);

        $objectionDeadlineAt = $flag->created_at?->copy()->addDays(2);
        if ($flag->status === 'removed') {
            return back()->with('error', 'This flag was removed and is no longer open for objection.');
        }
        if ($objectionDeadlineAt && now()->gt($objectionDeadlineAt)) {
            return back()->with('error', sprintf(
                'Objection window closed on %s.',
                $objectionDeadlineAt->toDateTimeString()
            ));
        }

        $flag->update([
            'objection_text' => trim($payload['objection_text']),
            'objection_status' => 'pending',
            'objection_response' => null,
            'objection_submitted_at' => now(),
            'objection_reviewed_by' => null,
            'objection_reviewed_at' => null,
            'status' => 'appealed',
        ]);

        return back()->with('success', 'Objection submitted to admin successfully.');
    }
}
