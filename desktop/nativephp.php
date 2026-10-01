<?php

use App\Providers\NativeAppServiceProvider;

/*
 * SchoolHub for Windows (NativePHP). Copied into the Windows build by
 * desktop/build-windows.ps1 as config/nativephp.php; the online server
 * never loads it.
 */
return [
    /**
     * Raise with every installer released (desktop/build-windows.ps1 -Version).
     */
    'version' => env('NATIVEPHP_APP_VERSION', '1.0.0'),

    'app_id' => env('NATIVEPHP_APP_ID', 'com.dualhub.schoolhub'),

    'deeplink_scheme' => env('NATIVEPHP_DEEPLINK_SCHEME'),

    'author' => env('NATIVEPHP_APP_AUTHOR', 'DualHub Technologies'),

    'copyright' => env('NATIVEPHP_APP_COPYRIGHT', '© DualHub Technologies'),

    'description' => env('NATIVEPHP_APP_DESCRIPTION', 'SchoolHub school management for Ugandan schools, working without internet.'),

    'website' => env('NATIVEPHP_APP_WEBSITE', 'https://www.schoolhubug.com'),

    'provider' => NativeAppServiceProvider::class,

    /**
     * Removed from the .env packed into the installer. The desktop .env has
     * none of these, but nothing secret may ever ship by mistake.
     */
    'cleanup_env_keys' => [
        'AWS_*',
        'AZURE_*',
        'GITHUB_*',
        'DO_SPACES_*',
        '*_SECRET',
        '*_PASSWORD',
        'BIFROST_*',
        'LICENCE_PRIVATE_KEY',
        'MAXMIND_*',
        'AFRICASTALKING_API_KEY',
        'NATIVEPHP_UPDATER_PATH',
        'NATIVEPHP_APPLE_ID',
        'NATIVEPHP_APPLE_ID_PASS',
        'NATIVEPHP_APPLE_TEAM_ID',
        'NATIVEPHP_AZURE_PUBLISHER_NAME',
        'NATIVEPHP_AZURE_ENDPOINT',
        'NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME',
        'NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME',
    ],

    /**
     * Left out of the installer: development files the app never uses.
     */
    'cleanup_exclude_files' => [
        'build',
        'temp',
        'content',
        'node_modules',
        '*/tests',
        'tests',
        'docs',
        'desktop',
        '.github',
        '.ai',
        'storage/logs/*',
        'storage/app/backups/*',
        'storage/framework/testing',
        'phpstan*.neon',
        'phpunit.xml',
        'AGENTS.md',
        'boost.json',
    ],

    /**
     * Automatic updates are off until a download location is set up; a new
     * version is installed over the old one, keeping the school's data.
     */
    'updater' => [
        'enabled' => env('NATIVEPHP_UPDATER_ENABLED', false),

        'default' => env('NATIVEPHP_UPDATER_PROVIDER', 'github'),

        'providers' => [
            'github' => [
                'driver' => 'github',
                'repo' => env('GITHUB_REPO'),
                'owner' => env('GITHUB_OWNER'),
                'token' => env('GITHUB_TOKEN'),
                'vPrefixedTagName' => env('GITHUB_V_PREFIXED_TAG_NAME', true),
                'private' => env('GITHUB_PRIVATE', false),
                'autoupdate_token' => env('GITHUB_AUTOUPDATE_TOKEN'),
                'channel' => env('GITHUB_CHANNEL', 'latest'),
                'releaseType' => env('GITHUB_RELEASE_TYPE', 'draft'),
            ],
        ],
    ],

    /**
     * Background jobs (student imports and the like), with room for a
     * whole school's file.
     */
    'queue_workers' => [
        'default' => [
            'queues' => ['default'],
            'memory_limit' => 256,
            'timeout' => 300,
            'sleep' => 3,
        ],
    ],

    'prebuild' => [],

    'postbuild' => [],

    /**
     * The Windows installer. The school's data stays when SchoolHub is
     * uninstalled, so reinstalling or upgrading never loses it.
     *
     * @see https://www.electron.build/generated/nsisoptions
     */
    'nsis' => [
        'delete_app_data_on_uninstall' => env('NATIVEPHP_NSIS_DELETE_APP_DATA', false),
    ],

    'binary_path' => env('NATIVEPHP_PHP_BINARY_PATH', null),
];
