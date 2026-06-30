<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'ContactType_Dozent_in',
    'entity' => 'ContactType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Dozent_in',
        'label' => E::ts('Dozent*in'),
        'description' => E::ts('Seminartrainer*in'),
        'parent_id.name' => 'Individual',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
