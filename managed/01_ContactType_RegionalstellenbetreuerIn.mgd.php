<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'ContactType_RegionalstellenbetreuerIn',
    'entity' => 'ContactType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'RegionalstellenbetreuerIn',
        'label' => E::ts('RegionalstellenbetreuerIn'),
        'description' => E::ts('RegionalstellenbetreuerIn'),
        'parent_id.name' => 'Individual',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
