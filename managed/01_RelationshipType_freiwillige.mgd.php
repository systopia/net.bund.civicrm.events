<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'RelationshipType_freiwillige',
    'entity' => 'RelationshipType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name_a_b' => 'ist Freiwillige* bei',
        'label_a_b' => E::ts('ist Freiwillige* bei'),
        'name_b_a' => 'hat Freiwillige*',
        'label_b_a' => E::ts('hat Freiwillige*'),
        'contact_type_a' => 'Individual',
        'contact_type_b' => 'Organization',
        'contact_sub_type_a' => 'Freiwillige',
        'contact_sub_type_b' => 'Einsatzstelle',
        'relationship_block.is_relationship_block_on_summary' => TRUE,
      ],
      'match' => [
        'name_a_b',
        'name_b_a',
      ],
    ],
  ],
];
