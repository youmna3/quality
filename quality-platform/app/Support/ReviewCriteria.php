<?php

namespace App\Support;

class ReviewCriteria
{
    public const BASE_SCORE = 5;

    public static function groups(): array
    {
        return [
            [
                'key' => 'setup',
                'label' => 'Setup',
                'criteria' => [
                    self::criterion(
                        'camera_quality',
                        'Camera Quality',
                        'S',
                        [
                            'S - Camera Quality: The instructor’s video was clear and stable throughout the session.',
                            'S - Camera Quality: High-quality visuals made it easy to stay engaged and follow along.',
                        ],
                        [
                            'S - Camera Quality: The camera angle was occasionally too low, making it difficult to see the instructor’s face clearly.',
                            'S - Camera Quality: The camera was placed too far from the instructor, making it hard for students to see the face clearly.',
                            'S - Camera Quality: The image quality needs improvement, as the video appears dark and blurry.',
                        ]
                    ),
                    self::criterion(
                        'microphone',
                        'Microphone Quality',
                        'S',
                        [
                            'S - Microphone Quality: The audio was consistently clear and of high quality.',
                            'S - Microphone Quality: The instructor’s voice was easy to understand throughout the session.',
                        ],
                        [
                            'S - Microphone Quality: Kindly enhance microphone quality to reduce background noise during sessions.',
                            'S - Microphone Quality: The microphone quality needs improvement, as the instructor’s voice was difficult to hear.',
                            'S - Microphone Quality: Kindly ensure your microphone remains muted until the student joins the session.',
                        ]
                    ),
                    self::criterion(
                        'environment',
                        'Environment',
                        'S',
                        [
                            'S - Environment: The surroundings were well-arranged, contributing to a professional look.',
                            'S - Environment: Proper lighting was used, ensuring clear visibility.',
                        ],
                        [
                            'S - Environment: Not using the official iSchool virtual background is a violation of session guidelines.',
                            'S - Environment: Insufficient lighting made it hard to see the instructor’s face.',
                            'S - Environment: Instructors are expected to conduct sessions in a quiet and distraction-free environment.',
                        ]
                    ),
                    self::criterion(
                        'internet_quality',
                        'Internet Quality',
                        'S',
                        [
                            'S - Internet Quality: The instructor maintained a strong and reliable internet connection.',
                            'S - Internet Quality: Stability in the connection allowed for smooth online learning.',
                        ],
                        [
                            'S - Internet Quality: Occasional lags in the internet connection led to disruptions in class flow.',
                            'S - Internet Quality: Video quality suffered due to an unstable internet connection.',
                            'S - Internet Quality: Improvements in internet connection strength are necessary to address buffering and delays.',
                        ]
                    ),
                    self::criterion(
                        'dress_code',
                        'Dress Code',
                        'S',
                        [
                            'S - Dress Code: The instructor was appropriately dressed, reflecting professional standards.',
                            'S - Dress Code: The instructor maintained a professional appearance throughout the session.',
                        ],
                        [
                            'S - Dress Code: Wearing a hood during the session violates our dress code policy.',
                            'S - Dress Code: Kindly adhere to a professional dress code by refraining from wearing hats or caps.',
                            'S - Dress Code: Kindly ensure a professional appearance and adherence to semi-formal/formal attire.',
                        ]
                    ),
                ],
            ],
            [
                'key' => 'attitude',
                'label' => 'Attitude',
                'criteria' => [
                    self::criterion(
                        'voice_tone_clarity',
                        'Voice Tone & Clarity',
                        'A',
                        [
                            'A - Voice Tone & Clarity: The instructor’s speaking voice is clear and easy to follow.',
                            'A - Voice Tone & Clarity: The instructor’s tone is engaging and maintains students’ attention.',
                        ],
                        [
                            'A - Voice Tone & Clarity: Sometimes, the instructor’s voice lacks clarity, leading to confusion.',
                            'A - Voice Tone & Clarity: Varying your tone is essential to keeping students engaged and active.',
                            'A - Voice Tone & Clarity: Please consider speaking more slowly to ensure your message is clearly understood.',
                        ]
                    ),
                    self::criterion(
                        'language_used',
                        'Language Used',
                        'A',
                        [
                            'A - Language Used: The instructor consistently uses professional language.',
                            'A - Language Used: Professional language contributes to a respectful learning environment.',
                        ],
                        [
                            'A - Language Used: Occasional casual expressions did not align with the desired professional tone.',
                            'A - Language Used: Please show respect for student effort and avoid mocking.',
                            'A - Language Used: Kindly avoid discussing unrelated personal topics during the session.',
                        ]
                    ),
                    self::criterion(
                        'session_initiation_closure',
                        'Session Initiation & Closure',
                        'A',
                        [
                            'A - Session Initiation & Closure: The session started warmly with clear instructions.',
                            'A - Session Initiation & Closure: The session ended with a clear summary and next steps.',
                        ],
                        [
                            'A - Session Initiation & Closure: The session began without sufficient clarity or warmth.',
                            'A - Session Initiation & Closure: Kindly begin each session by introducing yourself clearly.',
                            'A - Session Initiation & Closure: Please ensure the session ends with a clear and respectful conclusion.',
                        ]
                    ),
                    self::criterion(
                        'friendliness',
                        'Friendliness',
                        'A',
                        [
                            'A - Friendliness: The instructor demonstrated a friendly and approachable demeanor.',
                            'A - Friendliness: The instructor created a welcoming and engaging atmosphere.',
                        ],
                        [
                            'A - Friendliness: Occasional lapses in sociability impacted student engagement.',
                            'A - Friendliness: Kindly use a more supportive tone when students make mistakes.',
                            'A - Friendliness: Please avoid over-familiar conversations and keep interactions professional.',
                        ]
                    ),
                ],
            ],
            [
                'key' => 'preparation',
                'label' => 'Preparation',
                'criteria' => [
                    self::criterion(
                        'session_study',
                        'Session study',
                        'P',
                        [
                            'P - Session study: The instructor demonstrated a thorough understanding of session concepts.',
                            'P - Session study: The instructor accurately responded to student questions with confidence.',
                        ],
                        [
                            'P - Session study: Occasional gaps in understanding affected explanation quality.',
                            'P - Session study: Instructors are expected to study each session in advance for live implementation.',
                            'P - Session study: There is room for improvement in preparing for the practical part of the session.',
                        ]
                    ),
                    self::criterion(
                        'project_software_slides',
                        'Project software & slides',
                        'P',
                        [
                            'P - Project software & slides: All required software and slides were ready at session start.',
                            'P - Project software & slides: Preparedness contributed to effective and uninterrupted teaching.',
                        ],
                        [
                            'P - Project software & slides: Please open required software before session start.',
                            'P - Project software & slides: Kindly ensure the presentation is shared in slide show mode.',
                            'P - Project software & slides: Avoid showing internal tools or unrelated content during session.',
                        ]
                    ),
                    self::criterion(
                        'knowledge_about_subject',
                        'Knowledge About Subject',
                        'P',
                        [
                            'P - Knowledge About Subject: The instructor showed strong and comprehensive subject knowledge.',
                            'P - Knowledge About Subject: Explanations reflected deep understanding of the topic.',
                        ],
                        [
                            'P - Knowledge About Subject: Some details were not explained accurately and need revision.',
                            'P - Knowledge About Subject: Additional practice is needed to ensure confidence across all concepts.',
                            'P - Knowledge About Subject: Clarification quality can be improved in advanced parts of the session.',
                        ]
                    ),
                ],
            ],
            [
                'key' => 'curriculum',
                'label' => 'Curriculum',
                'criteria' => [
                    self::criterion(
                        'slides_project_completion',
                        'Slides and project completion',
                        'C',
                        [
                            'C - Slides and project completion: All slides were explained clearly and project delivery was complete.',
                            'C - Slides and project completion: The instructor covered all content and completed the project thoroughly.',
                        ],
                        [
                            'C - Slides and project completion: Some slides were rushed and the project was not fully completed.',
                            'C - Slides and project completion: Ensure better time management to avoid omitting slides.',
                            'C - Slides and project completion: Full project implementation should be delivered step by step.',
                        ]
                    ),
                    self::criterion(
                        'tools_methodology',
                        'Tools used and Methodology',
                        'C',
                        [
                            'C - Tools used and Methodology: Zoom tools were used effectively to keep the session interactive.',
                            'C - Tools used and Methodology: Explanations were clear and supported by relevant examples.',
                        ],
                        [
                            'C - Tools used and Methodology: More consistent use of annotation tools can improve engagement.',
                            'C - Tools used and Methodology: Examples should be simplified to reduce student confusion.',
                            'C - Tools used and Methodology: Ensure shared content stays focused and free from distractions.',
                        ]
                    ),
                    self::criterion(
                        'homework',
                        'Homework',
                        'C',
                        [
                            'C - Homework: Homework follow-up was consistent and supported student progress.',
                            'C - Homework: The instructor reviewed homework and provided clear guidance.',
                        ],
                        [
                            'C - Homework: Checking completion only is not enough; review understanding with students.',
                            'C - Homework: Homework instructions should be communicated clearly before session end.',
                            'C - Homework: Ensure all students receive balanced homework feedback.',
                        ]
                    ),
                ],
            ],
            [
                'key' => 'teaching',
                'label' => 'Teaching',
                'criteria' => [
                    self::criterion(
                        'class_management',
                        'Class Management',
                        'T',
                        [
                            'T - Class Management: The instructor maintained a structured and productive learning environment.',
                            'T - Class Management: Session flow and student behavior were managed effectively.',
                        ],
                        [
                            'T - Class Management: The session lacked consistent structure, affecting flow and focus.',
                            'T - Class Management: Student interruptions were not managed effectively.',
                            'T - Class Management: Please maintain stronger control of participation and classroom rules.',
                        ]
                    ),
                    self::criterion(
                        'student_engagement',
                        'Student Engagement',
                        'T',
                        [
                            'T - Student Engagement: The instructor used interactive strategies that kept students engaged.',
                            'T - Student Engagement: Student participation was encouraged throughout the session.',
                        ],
                        [
                            'T - Student Engagement: Interaction levels were low and engagement opportunities were missed.',
                            'T - Student Engagement: The session was too one-directional with limited student participation.',
                            'T - Student Engagement: Encourage active discussion during concept explanation.',
                        ]
                    ),
                    self::criterion(
                        'all_students_involvement',
                        "All students' involvement",
                        'T',
                        [
                            "T - All Students’ Involvement: Time was distributed fairly and all students were involved.",
                            "T - All Students’ Involvement: The instructor ensured balanced participation across the group.",
                        ],
                        [
                            "T - All Students’ Involvement: Participation was dominated by a few students.",
                            "T - All Students’ Involvement: More effort is needed to include quieter students.",
                            "T - All Students’ Involvement: Time distribution was uneven among students.",
                        ]
                    ),
                    self::criterion(
                        'session_synchronization',
                        'Session Synchronization',
                        'T',
                        [
                            'T - Session Synchronization: The session followed a clear Learn-Make-Share flow.',
                            'T - Session Synchronization: Timing was managed effectively across session stages.',
                        ],
                        [
                            'T - Session Synchronization: Stage timing was inconsistent and affected session flow.',
                            'T - Session Synchronization: Better break timing and pacing are needed.',
                            'T - Session Synchronization: Kindly ensure the session starts and ends on time.',
                        ]
                    ),
                    self::criterion(
                        'project_implementation',
                        'Project Implementation & Activities',
                        'T',
                        [
                            'T - Project Implementation & Activities: Students were actively guided through project implementation.',
                            'T - Project Implementation & Activities: The project flow encouraged participation and understanding.',
                        ],
                        [
                            'T - Project Implementation & Activities: Students should implement live instead of copying ready code.',
                            'T - Project Implementation & Activities: Break the project into smaller steps for clearer understanding.',
                            'T - Project Implementation & Activities: More active guidance is needed during implementation.',
                        ]
                    ),
                ],
            ],
        ];
    }

    public static function criteriaMap(): array
    {
        $map = [];
        foreach (self::groups() as $group) {
            foreach ($group['criteria'] as $criterion) {
                $criterion['group_key'] = $group['key'];
                $criterion['group_label'] = $group['label'];
                $map[$criterion['key']] = $criterion;
            }
        }

        return $map;
    }

    public static function criterionOrder(): array
    {
        return array_keys(self::criteriaMap());
    }

    public static function criterionLabelsInOrder(): array
    {
        return array_map(
            fn (string $key) => self::criteriaMap()[$key]['label'],
            self::criterionOrder()
        );
    }

    public static function emptySelections(): array
    {
        $values = [];
        foreach (self::criterionOrder() as $key) {
            $values[$key] = [];
        }

        return $values;
    }

    public static function groupCriteriaKeys(): array
    {
        $map = [];
        foreach (self::groups() as $group) {
            $map[$group['label']] = array_map(
                fn (array $criterion) => $criterion['key'],
                $group['criteria']
            );
        }

        return $map;
    }

    public static function groupLabelsInOrder(): array
    {
        return array_map(fn (array $group) => $group['label'], self::groups());
    }

    public static function inferCriterionKeyFromComment(string $comment): ?string
    {
        $normalizedComment = strtolower(trim((string) preg_replace('/\s+/', ' ', $comment)));
        if ($normalizedComment === '') {
            return null;
        }

        if (preg_match('/^[a-z]\s*-\s*([^:]+):/i', $normalizedComment, $matches)) {
            $subcategory = self::normalizeDescriptor($matches[1] ?? '');
            foreach (self::criteriaMap() as $key => $criterion) {
                if ($subcategory === self::normalizeDescriptor($criterion['label'])) {
                    return $key;
                }
            }
        }

        foreach (self::criteriaMap() as $key => $criterion) {
            $label = self::normalizeDescriptor($criterion['label']);
            if ($label === '') {
                continue;
            }

            if (
                str_contains(self::normalizeDescriptor($normalizedComment), $label)
                || str_contains($normalizedComment, strtolower($criterion['prefix']))
            ) {
                return $key;
            }
        }

        return null;
    }

    public static function flagSubcategories(): array
    {
        $labels = self::criterionLabelsInOrder();

        return array_values(array_unique(array_filter($labels)));
    }

    public static function criterionKeyForSubcategory(string $subcategory): ?string
    {
        $target = self::normalizeDescriptor($subcategory);
        if ($target === '') {
            return null;
        }

        foreach (self::criteriaMap() as $key => $criterion) {
            if (self::normalizeDescriptor($criterion['label']) === $target) {
                return $key;
            }
        }

        return null;
    }

    private static function criterion(
        string $key,
        string $label,
        string $prefixCode,
        array $positive,
        array $negative
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'prefix' => $prefixCode.' - '.$label.':',
            'positive' => $positive,
            'negative' => $negative,
        ];
    }

    private static function normalizeDescriptor(string $value): string
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }
}
