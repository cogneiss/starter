<?php

declare(strict_types=1);

/**
 * The strings the interface renders on the client.
 *
 * PHP translation files stay canonical: this array is what the request hands to
 * the page as a prop, so a string has one home and one spelling rather than one
 * in a Blade view, one in a component and one nobody remembers writing.
 */
return [
    'language' => 'Language',

    'locale' => [
        'en' => 'English',
        'nl' => 'Nederlands',
    ],

    'impersonation' => [
        'banner' => 'You are impersonating this account as :name.',
        'banner_unknown' => 'You are impersonating this account.',
        'region' => 'Impersonation',
        'stop' => 'Stop impersonating',
    ],

    'announcements' => [
        'region' => 'Announcements',
        'dismiss' => 'Dismiss announcement',
        'title' => 'Announcements',
        'description' => 'Banners shown to members inside the app',
        'new' => 'New announcement',
        'edit' => 'Edit',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'title_field' => 'Title',
        'body_field' => 'Message',
        'level_field' => 'Level',
        'starts_field' => 'Starts',
        'ends_field' => 'Ends (optional)',
        'audience_field' => 'Audience',
        'audience_all' => 'Every organization',
        'level_info' => 'Info',
        'level_warning' => 'Warning',
    ],

    'credits' => [
        'starter' => 'Starter credit',
        'grant_title' => 'Grant AI credit',
        'grant_new' => 'Grant AI credit',
        'organization_field' => 'Organization',
        'amount_field' => 'Amount (micros, 1,000,000 is one dollar)',
        'reason_field' => 'Reason',
        'grant_submit' => 'Grant credit',
        'cancel' => 'Cancel',
        'granted' => 'AI credit granted.',
    ],

    'ai_draft' => [
        'button' => 'Draft with AI',
        'working' => 'Drafting…',
        'done' => 'Draft inserted. Review it before saving.',
        'failed' => 'The draft failed. Try again in a moment.',
    ],

    'dashboard' => [
        'activity' => [
            'title' => 'Activity',
            'description' => 'API requests per day over the last 30 days',
            'summary' => 'requests in the last 30 days',
            'empty' => 'No API requests in the last 30 days',
            'requests' => 'Requests',
        ],
    ],

    'assistant' => [
        'title' => 'Assistant',
        'new' => 'New conversation',
        'recent' => 'Recent conversations',
        'empty_title' => 'No conversations yet',
        'empty_body' => 'Ask the assistant about your organization. It reads your records and proposes changes for you to confirm.',
        'placeholder' => 'Ask about your organization',
        'send' => 'Send',
        'sending' => 'Thinking…',
        'you' => 'You',
        'assistant' => 'Assistant',
        'ai_label' => 'AI-generated',
        'intro' => 'Ask a question to start. The assistant never changes anything without your confirmation.',
        'failed' => 'The assistant could not answer. Your message was not lost, try sending it again.',
        'quota' => 'Your organization has used its AI allowance for now. It resets shortly.',
        'back' => 'All conversations',
    ],

    'nav' => [
        'assistant' => 'Assistant',
        'admin' => 'Admin',
        'dashboard' => 'Dashboard',
        'documentation' => 'Documentation',
        'repository' => 'Repository',
    ],

    'site' => [
        'nav' => 'Site',
        'home' => 'Home',
        'blog' => 'Blog',
        'changelog' => 'Changelog',
        'welcome_title' => 'Welcome',
        'welcome_description' => 'A Laravel and React starter kit with organizations, an API, webhooks and an AI layer built in.',
        'blog_description' => 'News and notes from the team.',
        'blog_empty' => 'No posts yet.',
        'blog_back' => 'All posts',
        'blog_by' => 'By',
        'changelog_description' => 'What changed in each release.',
    ],

    'member_remove' => [
        'button' => 'Remove',
        'title' => 'Remove this member?',
        'description' => 'They lose access to the organization straight away. This cannot be undone.',
        'confirm' => 'Remove',
        'cancel' => 'Cancel',
    ],
];
