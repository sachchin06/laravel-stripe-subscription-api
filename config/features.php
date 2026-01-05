<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines which features are available for different subscription
    | plans and usage limits for each plan tier.
    |
    */

    'free' => [
        'basic_api_access',
        'profile_management',
    ],

    'plans' => [
        'basic' => [
            'basic_api_access',
            'profile_management',
            'advanced_analytics',
            'email_support',
        ],
        
        'pro' => [
            'basic_api_access',
            'profile_management',
            'advanced_analytics',
            'email_support',
            'priority_support',
            'custom_integrations',
            'team_collaboration',
            'advanced_reporting',
        ],
    ],

    'limits' => [
        'free' => [
            'api_calls' => 100,
            'storage_mb' => 50,
            'team_members' => 1,
        ],
        
        'plans' => [
            'basic' => [
                'api_calls' => 10000,
                'storage_mb' => 1000,
                'team_members' => 5,
            ],
            
            'pro' => [
                'api_calls' => null, // unlimited
                'storage_mb' => 10000,
                'team_members' => null, // unlimited
            ],
        ],
    ],
];