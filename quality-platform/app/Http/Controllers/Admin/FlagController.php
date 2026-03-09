<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlagController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'color' => $request->string('color')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'search' => $request->string('search')->toString(),
        ];

        $flags = Flag::query()
            ->with([
                'tutor:id,tutor_code,name_en',
                'review:id,reviewer_name,session_date,slot,group_code,recorded_link',
            ])
            ->when($filters['color'], fn ($query, $color) => $query->where('color', $color))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('subcategory', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('objection_text', 'like', "%{$search}%")
                        ->orWhereHas('tutor', function ($tutorQuery) use ($search) {
                            $tutorQuery
                                ->where('tutor_code', 'like', "%{$search}%")
                                ->orWhere('name_en', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Flag $flag) => [
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
                'screenshot_url' => $flag->screenshot_path ? asset('storage/'.$flag->screenshot_path) : null,
                'created_at' => $flag->created_at?->toDateTimeString(),
                'tutor' => $flag->tutor
                    ? [
                        'tutor_code' => $flag->tutor->tutor_code,
                        'name_en' => $flag->tutor->name_en,
                    ]
                    : null,
                'review' => $flag->review
                    ? [
                        'reviewer_name' => $flag->review->reviewer_name,
                        'session_date' => $flag->review->session_date?->toDateString(),
                        'slot' => $flag->review->slot,
                        'group_code' => $flag->review->group_code,
                        'recorded_link' => $flag->review->recorded_link,
                    ]
                    : null,
            ]);

        return Inertia::render('Admin/Flags/Index', [
            'filters' => $filters,
            'flags' => $flags,
            'statusOptions' => ['open', 'accepted', 'removed', 'partial', 'appealed', 'resolved'],
            'colorOptions' => ['yellow', 'red', 'both'],
        ]);
    }
}
