<?php

return [
    'title' => 'Account security',
    'navigation_label' => 'Account security',
    'fields' => [
        'password_min_length' => 'Minimum password length',
        'password_letters' => 'Require letters',
        'password_mixed_case' => 'Require uppercase and lowercase letters',
        'password_numbers' => 'Require numbers',
        'password_symbols' => 'Require symbols',
        'mfa_required' => 'Require two-factor authentication',
    ],
    'password' => [
        'title' => 'Password strength',
        'description' => 'Shared by all backend administrator and employee accounts. Applies when an administrator creates or changes an account password, or when a user changes or resets their password. Existing passwords remain valid until changed. Applicant portal accounts are outside this policy.',
        'characters' => 'characters',
        'length_help' => 'Choose 12–72 characters. The existing minimum is 12. Passwords must also fit within 72 bytes and cannot contain null characters; non-ASCII characters may use multiple bytes.',
    ],
    'help' => [
        'password_letters' => 'Require at least one letter.',
        'password_mixed_case' => 'Require at least one uppercase letter and one lowercase letter. This also requires letters even if the separate letters option is off.',
        'password_numbers' => 'Require at least one digit.',
        'password_symbols' => 'Require at least one symbol, such as ! or @.',
    ],
    'mfa' => [
        'title' => 'Two-factor authentication',
        'description' => 'Uses the existing authenticator app and recovery codes. This policy covers backend access and is shared across organisations.',
        'help' => 'When enabled, backend users without an authenticator must set one up before continuing, including users already signed in. Users with an authenticator still verify it at login. Save recovery codes in a safe place. Under the existing authentication rules, enterprise SSO is disabled while this requirement is active; prepare password login for SSO-only backend accounts before enabling it to avoid locking them out. Disabling this requirement keeps existing authenticators enabled and lets users manage them in their profile.',
    ],
    'errors' => [
        'stale' => 'Account security settings were changed by another administrator. Refresh the page and try again.',
        'database_required' => 'Account security settings require a database settings repository.',
    ],
];
