<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'ContactType_Einsatzstelle',
    'entity' => 'ContactType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Einsatzstelle',
        'label' => E::ts('Einsatzstelle'),
        'description' => E::ts('Einsatzstelle'),
        'icon' => 'fa-thumb-tack',
        'parent_id.name' => 'Organization',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
