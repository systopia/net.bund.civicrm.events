<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'ContactType_Freiwillige',
    'entity' => 'ContactType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Freiwillige',
        'label' => E::ts('Freiwillige'),
        'description' => E::ts('Freiwillige'),
        'parent_id.name' => 'Individual',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
