<?php

/**
 * Matter stream / forum labels and allowed "our party" procedural roles (Australian practice).
 * Stream is stored on matters.stream; party role values on client_matters.our_party_role.
 */
return [
    'streams' => [
        'civil_vic' => 'Civil — Victorian courts',
        'criminal' => 'Criminal',
        'family' => 'Family law (FCFCOA)',
        'property' => 'Property & real estate',
        'corporate' => 'Corporate & commercial',
        'employment_fwc' => 'Employment / Fair Work',
        'consumer' => 'Consumer',
        'banking' => 'Banking & finance',
        'taxation' => 'Taxation',
        'ip' => 'Intellectual property',
        'constitutional' => 'Constitutional / writ',
        'revenue' => 'Revenue & land',
        'motor_accident' => 'Motor accident / TAC',
        'vcat' => 'VCAT / tribunal',
        'migration_merits' => 'Migration — merits / admin review',
        'judicial_review' => 'Judicial review',
        'general' => 'General / other',
    ],

    /**
     * Allowed values for client_matters.our_party_role per stream (value => label).
     */
    'party_roles_by_stream' => [
        'civil_vic' => [
            'plaintiff' => 'Plaintiff',
            'defendant' => 'Defendant',
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
            'appellant' => 'Appellant',
        ],
        'criminal' => [
            'accused' => 'Accused',
            'defendant' => 'Defendant (criminal)',
        ],
        'family' => [
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'property' => [
            'plaintiff' => 'Plaintiff',
            'defendant' => 'Defendant',
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'corporate' => [
            'plaintiff' => 'Plaintiff',
            'defendant' => 'Defendant',
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'employment_fwc' => [
            'applicant' => 'Applicant (employee)',
            'respondent' => 'Respondent (employer)',
        ],
        'consumer' => [
            'complainant' => 'Complainant / applicant',
            'respondent' => 'Respondent',
        ],
        'banking' => [
            'plaintiff' => 'Plaintiff',
            'defendant' => 'Defendant',
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'taxation' => [
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'ip' => [
            'plaintiff' => 'Plaintiff',
            'defendant' => 'Defendant',
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'constitutional' => [
            'applicant' => 'Applicant',
            'petitioner' => 'Petitioner',
            'respondent' => 'Respondent',
        ],
        'revenue' => [
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'motor_accident' => [
            'claimant' => 'Claimant',
            'defendant' => 'Defendant',
        ],
        'vcat' => [
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'migration_merits' => [
            'applicant' => 'Applicant',
        ],
        'judicial_review' => [
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
        ],
        'general' => [
            'plaintiff' => 'Plaintiff',
            'defendant' => 'Defendant',
            'applicant' => 'Applicant',
            'respondent' => 'Respondent',
            'accused' => 'Accused',
            'other' => 'Other (see case detail)',
        ],
    ],
];
