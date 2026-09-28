<?php

// Department forms kept on a job and printed as PDFs. Field types: text,
// textarea, date, select (options), checklist (items), rows (columns).
// `signoff` adds the client sign-off block to the PDF.

return [
    'brief' => [
        'label' => 'Creative Brief',
        'department' => 'brand',
        'intro' => 'Agree the scope with the client before design starts. Send it for the client to check and sign.',
        'signoff' => 'Brief agreed by client',
        'sections' => [
            'The project' => [
                'background' => ['label' => 'Background', 'type' => 'textarea', 'hint' => 'The business, the product and why this work is needed now'],
                'objective' => ['label' => 'Objective', 'type' => 'textarea', 'hint' => 'What success looks like'],
                'audience' => ['label' => 'Target audience', 'type' => 'textarea'],
                'message' => ['label' => 'Key message', 'type' => 'textarea', 'hint' => 'The one thing the audience should remember'],
            ],
            'The work' => [
                'deliverables' => ['label' => 'Deliverables', 'type' => 'textarea', 'hint' => 'One per line, with sizes and formats'],
                'tone' => ['label' => 'Tone and style', 'type' => 'text', 'hint' => 'e.g. clean, premium, friendly'],
                'mandatory' => ['label' => 'Must include', 'type' => 'textarea', 'hint' => 'Logo, colours, fonts, taglines, legal text'],
                'avoid' => ['label' => 'Avoid', 'type' => 'textarea'],
                'references' => ['label' => 'References and links', 'type' => 'textarea'],
            ],
            'Timeline and rights' => [
                'first_draft' => ['label' => 'First draft by', 'type' => 'date'],
                'final_due' => ['label' => 'Final files by', 'type' => 'date'],
                'revisions' => ['label' => 'Revision rounds included', 'type' => 'select', 'options' => ['1', '2', '3']],
                'usage' => ['label' => 'Usage rights', 'type' => 'select', 'options' => ['Digital only', 'Print only', 'Digital and print', 'All media']],
            ],
        ],
    ],

    'uat' => [
        'label' => 'UAT / Go-Live Sign-off',
        'department' => 'tech',
        'intro' => 'Walk the client through the system, tick what was tested and get their sign-off before go-live.',
        'signoff' => 'Accepted for go-live by client',
        'sections' => [
            'The system' => [
                'system' => ['label' => 'System / website', 'type' => 'text', 'hint' => 'Name and URL'],
                'version' => ['label' => 'Version or build', 'type' => 'text'],
                'test_date' => ['label' => 'Tested on', 'type' => 'date'],
                'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['Staging', 'Live']],
            ],
            'Checklist' => [
                'checks' => ['label' => 'What was tested', 'type' => 'checklist', 'items' => [
                    'Every page opens on desktop, tablet and mobile',
                    'Content, spelling and images checked by client',
                    'Links and buttons go to the right place',
                    'Forms send to the right email and are saved',
                    'Login, roles and passwords work',
                    'Payments / integrations tested end to end',
                    'SSL (https) active, no browser warnings',
                    'Page speed acceptable',
                    'SEO basics: titles, descriptions, sitemap',
                    'Backup taken before go-live',
                    'Admin access and user guide handed over',
                ]],
            ],
            'Result' => [
                'issues' => ['label' => 'Issues found', 'type' => 'textarea', 'hint' => 'One per line, with who fixes it and by when'],
                'result' => ['label' => 'Result', 'type' => 'select', 'options' => ['Passed', 'Passed with minor fixes', 'Not passed, retest needed']],
                'go_live' => ['label' => 'Go-live date', 'type' => 'date'],
                'warranty_until' => ['label' => 'Bug-fix warranty until', 'type' => 'date'],
            ],
        ],
    ],

    'runsheet' => [
        'label' => 'Event Run Sheet',
        'department' => 'event',
        'intro' => 'The minute-by-minute plan for the day, shared with the client and the crew.',
        'signoff' => null,
        'sections' => [
            'The event' => [
                'event_name' => ['label' => 'Event', 'type' => 'text'],
                'event_date' => ['label' => 'Date', 'type' => 'date'],
                'venue' => ['label' => 'Venue', 'type' => 'text'],
                'onsite_pic' => ['label' => 'On-site PIC and phone', 'type' => 'text'],
                'client_pic' => ['label' => 'Client PIC and phone', 'type' => 'text'],
                'guests' => ['label' => 'Expected guests', 'type' => 'text'],
            ],
            'Programme' => [
                'programme' => ['label' => 'Run of show', 'type' => 'rows', 'columns' => ['Time', 'Activity', 'Who', 'Notes']],
            ],
            'Crew and logistics' => [
                'crew' => ['label' => 'Crew', 'type' => 'rows', 'columns' => ['Name', 'Role', 'Phone', 'Call time']],
                'equipment' => ['label' => 'Equipment and setup', 'type' => 'textarea', 'hint' => 'One per line'],
                'setup_time' => ['label' => 'Setup starts', 'type' => 'text'],
                'dismantle_time' => ['label' => 'Dismantle by', 'type' => 'text'],
                'emergency' => ['label' => 'Emergency contacts and nearest clinic', 'type' => 'textarea'],
            ],
        ],
    ],
];
