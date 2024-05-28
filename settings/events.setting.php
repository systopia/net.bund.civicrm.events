<?php
/*-------------------------------------------------------+
| BUND Event Customisations                              |
| Copyright (C) 2024 SYSTOPIA                            |
| Author: C. Jana (jana@systopia.de)                     |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Events_ExtensionUtil as E;

/*
* Settings metadata file
*/
return [
  'bund_event_types' => [
    'name' => 'bund_event_types',
    'type' => 'String',
    'default' => 1,
    'html_type' => 'select',
    'html_attributes' => [
      'class' => 'crm-select2',
      'multiple' => 1,
    ],
    'title' => E::ts("Event Types (for Volunteers)"),
    'is_domain' => 1,
    'is_contact' => 0,
    'pseudoconstant' => [
      'callback' => 'CRM_Events_Form_Settings::getEventTypes',
    ],
  ],
  'bund_event_relationship_types' => [
    'name' => 'bund_event_relationship_types',
    'type' => 'String',
    'default' => 1,
    'html_type' => 'select',
    'html_attributes' => [
      'class' => 'crm-select2',
      'multiple' => 1,
    ],
    'title' => E::ts("Relationship Types"),
    'is_domain' => 1,
    'is_contact' => 0,
    'pseudoconstant' => [
      'callback' => 'CRM_Events_Form_Settings::getEventRelationshipTypes',
    ],
  ],
  'bund_event_recipient_ids' => [
    'name' => 'bund_event_recipient_ids',
    'type' => 'String',
    'serialization' => CRM_Core_DAO::SERIALIZE_COMMA,
    'default' => "2886",
    'entity_reference_options' => ['entity' => 'contact'],
    'html_type' => 'select',
    'html_attributes' => [
      'class' => 'crm-select2',
      'multiple' => 1,
    ],
    'title' => E::ts("Contacts to receive notifications (comma separated contact ids)"),
    'is_domain' => 1,
    'is_contact' => 0,
  ],
  'bund_event_from_email_address' => [
    'name' => 'bund_event_from_email_address',
    'type' => 'String',
    'default' => '"BUND Bundesfreiwilligendienst" <bundesfreiwilligendienst@bund.net>',
    'html_type' => 'select',
    'html_attributes' => [
      'class' => 'crm-select2',
      'multiple' => 1,
    ],
    'title' => E::ts("From email address"),
    'is_domain' => 1,
    'is_contact' => 0,
    'pseudoconstant' => [
      'callback' => 'CRM_Events_Form_Settings::getFromEmailAddresses',
    ],
  ],
];
