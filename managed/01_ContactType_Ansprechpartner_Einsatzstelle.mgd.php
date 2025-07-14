<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'ContactType_Ansprechpartner_Einsatzstelle',
    'entity' => 'ContactType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Ansprechpartner_Einsatzstelle',
        'label' => E::ts('Ansprechpartner Einsatzstelle'),
        'description' => E::ts('Ansprechpartner Einsatzstelle'),
        'parent_id.name' => 'Individual',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
