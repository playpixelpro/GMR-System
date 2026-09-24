<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active NFA Rule Profile
    |--------------------------------------------------------------------------
    |
    | Supported profiles: "standard", "strict", "field"
    |
    */
    'active_profile' => env('NFA_ACTIVE_PROFILE', 'standard'),

    /*
    |--------------------------------------------------------------------------
    | NFA Milling Profiles
    |--------------------------------------------------------------------------
    */
    'profiles' => [
        'standard' => [
            'name' => 'NFA Standard Milling Recovery Profile',
            'pmr' => [
                'required_trials' => 5,
                'minimum_valid_trials' => 3,
                'outlier_tolerance_percent' => 0.02,
                'max_cv_percent' => 5.00,
            ],
            'amr' => [
                'required_trials' => 3,
                'minimum_valid_trials' => 2,
                'outlier_tolerance_percent' => 0.02,
            ],
        ],
        'strict' => [
            'name' => 'NFA Strict Laboratory Audit Profile',
            'pmr' => [
                'required_trials' => 5,
                'minimum_valid_trials' => 4,
                'outlier_tolerance_percent' => 0.015,
                'max_cv_percent' => 3.00,
            ],
            'amr' => [
                'required_trials' => 3,
                'minimum_valid_trials' => 2,
                'outlier_tolerance_percent' => 0.015,
            ],
        ],
        'field' => [
            'name' => 'NFA Field Verification Profile',
            'pmr' => [
                'required_trials' => 5,
                'minimum_valid_trials' => 3,
                'outlier_tolerance_percent' => 0.025,
                'max_cv_percent' => 7.00,
            ],
            'amr' => [
                'required_trials' => 3,
                'minimum_valid_trials' => 2,
                'outlier_tolerance_percent' => 0.02,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PMR Defaults & Overrides
    |--------------------------------------------------------------------------
    */
    'pmr' => [
        'required_trials' => (int) env('NFA_PMR_REQUIRED_TRIALS', 5),
        'minimum_valid_trials' => (int) env('NFA_PMR_MINIMUM_VALID_TRIALS', 3),
        'outlier_tolerance_percent' => (float) env('NFA_PMR_OUTLIER_TOLERANCE', 0.02),
        'max_cv_percent' => (float) env('NFA_PMR_MAX_CV_PERCENT', 5.00),
    ],
];
