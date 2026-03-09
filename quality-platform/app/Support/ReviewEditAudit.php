<?php

namespace App\Support;

use App\Models\Review;
use App\Models\ReviewEditLog;
use App\Models\User;

class ReviewEditAudit
{
    public static function snapshot(Review $review): array
    {
        $review->loadMissing('flags:id,review_id,color,subcategory,reason,duration_text,status');

        return [
            'tutor_role' => $review->tutor_role,
            'session_date' => $review->session_date?->toDateString(),
            'slot' => $review->slot,
            'group_code' => $review->group_code,
            'recorded_link' => $review->recorded_link,
            'issue_text' => $review->issue_text,
            'positive_comments_json' => self::normalizeNestedArray($review->positive_comments_json ?? []),
            'negative_comments_json' => self::normalizeNestedArray($review->negative_comments_json ?? []),
            'score_breakdown_json' => $review->score_breakdown_json ?? [],
            'total_score' => $review->total_score !== null ? (float) $review->total_score : null,
            'flags' => $review->flags
                ->map(fn ($flag) => [
                    'color' => (string) $flag->color,
                    'subcategory' => (string) $flag->subcategory,
                    'reason' => (string) $flag->reason,
                    'duration_text' => (string) ($flag->duration_text ?? ''),
                    'status' => (string) ($flag->status ?? 'open'),
                ])
                ->sortBy(fn (array $flag) => implode('|', $flag))
                ->values()
                ->all(),
        ];
    }

    public static function diff(array $before, array $after): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        $changes = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if (self::sameValue($beforeValue, $afterValue)) {
                continue;
            }

            $changes[$key] = [
                'before' => $beforeValue,
                'after' => $afterValue,
            ];
        }

        return $changes;
    }

    public static function log(Review $review, User $actor, array $before, array $after): ?ReviewEditLog
    {
        $changes = self::diff($before, $after);
        if ($changes === []) {
            return null;
        }

        return ReviewEditLog::query()->create([
            'review_id' => $review->id,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'changed_fields_json' => $changes,
            'before_snapshot_json' => $before,
            'after_snapshot_json' => $after,
        ]);
    }

    private static function sameValue(mixed $before, mixed $after): bool
    {
        return json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            === json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function normalizeNestedArray(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $values) {
            if (! is_array($values)) {
                continue;
            }

            $normalized[$key] = array_values(array_unique(array_map(
                fn ($value) => trim((string) $value),
                array_filter($values, fn ($value) => trim((string) $value) !== '')
            )));
        }

        ksort($normalized);

        return $normalized;
    }
}
