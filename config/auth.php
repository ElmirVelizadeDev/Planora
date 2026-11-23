<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one user table or model in the application and you want to have
    | separate password reset settings based on the specific user types.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | times out and the user is prompted to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => 10800,

    'admin_mfa' => [
        'login_challenge_minutes' => (int) env('ADMIN_MFA_LOGIN_CHALLENGE_MINUTES', 5),
        'enrollment_challenge_minutes' => (int) env('ADMIN_MFA_ENROLLMENT_CHALLENGE_MINUTES', 10),
        'enrollment_minutes' => (int) env('ADMIN_MFA_ENROLLMENT_MINUTES', 10),
        'step_up_grant_seconds' => (int) env('ADMIN_MFA_STEP_UP_GRANT_SECONDS', 60),
        'challenge_max_attempts' => (int) env('ADMIN_MFA_CHALLENGE_MAX_ATTEMPTS', 5),
        'challenge_window_minutes' => (int) env('ADMIN_MFA_CHALLENGE_WINDOW_MINUTES', 5),
        'challenge_lockout_minutes' => (int) env('ADMIN_MFA_CHALLENGE_LOCKOUT_MINUTES', 5),
        'account_max_attempts' => (int) env('ADMIN_MFA_ACCOUNT_MAX_ATTEMPTS', 15),
        'account_window_minutes' => (int) env('ADMIN_MFA_ACCOUNT_WINDOW_MINUTES', 15),
        'account_lockout_minutes' => (int) env('ADMIN_MFA_ACCOUNT_LOCKOUT_MINUTES', 15),
        'login_ip_attempts_per_minute' => (int) env('ADMIN_MFA_LOGIN_IP_ATTEMPTS_PER_MINUTE', 10),
        'enrollment_ip_attempts_per_minute' => (int) env('ADMIN_MFA_ENROLLMENT_IP_ATTEMPTS_PER_MINUTE', 5),
        'step_up_ip_attempts_per_minute' => (int) env('ADMIN_MFA_STEP_UP_IP_ATTEMPTS_PER_MINUTE', 5),
    ],

    'password_policy' => [
        'check_breaches' => (bool) env('PASSWORD_BREACH_CHECK_ENABLED', true),
    ],

    'email_enumeration_min_response_ms' => (int) env('AUTH_ENUMERATION_MIN_RESPONSE_MS', 250),

];
