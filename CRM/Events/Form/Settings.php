<?php
/*-------------------------------------------------------+
| BUND Event Customisations                              |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
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

use Civi\Api4\OptionValue;
use Civi\Api4\ParticipantStatusType;
use CRM_Events_ExtensionUtil as E;

/**
 * General settings regarding BUND events
 */
class CRM_Events_Form_Settings extends CRM_Core_Form {

  public const SETTINGS = [
    'bund_event_types',
    'bund_event_relationship_types',
    'bund_event_recipient_ids',
    'bund_event_from_email_address',
    'bund_event_participant_status_types',
  ];

  public function buildQuickForm() {
    $this->setTitle(E::ts('BUND Event Configuration'));

    $this->add(
        'select',
        'bund_event_types',
        E::ts('Event Types (for Volunteers)'),
        $this->getEventTypes(),
        FALSE,
        ['class' => 'crm-select2', 'multiple' => 'multiple', 'placeholder' => E::ts('all')]
    );
    $this->add(
        'select',
        'bund_event_relationship_types',
        E::ts('Relationship Types'),
        $this->getEventRelationshipTypes(),
        FALSE,
        ['class' => 'crm-select2', 'multiple' => 'multiple', 'placeholder' => E::ts('disabled')]
    );
    $this->add(
        'text',
        'bund_event_recipient_ids',
        E::ts('Contacts to receive notifications (comma separated contact ids)'),
        FALSE,
        FALSE,
        ['class' => 'crm-select2', 'multiple' => 'multiple', 'placeholder' => E::ts('none')]
    );
    $this->add(
        'select',
        'bund_event_from_email_address',
        E::ts('From email address'),
        $this->getFromEmailAddresses(),
        TRUE,
        ['class' => 'crm-select2', 'placeholder' => E::ts('none')]
    );
    $this->add(
            'select',
            'bund_event_participant_status_types',
            E::ts('Check event registrations with the following statuses'),
            $this->getParticipantStatusType(),
            TRUE,
            ['class' => 'crm-select2', 'multiple' => 'multiple', 'placeholder' => E::ts('none')]
    );

    $this->addButtons(
        [
            [
              'type' => 'submit',
              'name' => E::ts('Save'),
              'isDefault' => TRUE,
            ],
        ]
    );

    // set current values
    foreach (self::SETTINGS as $field_name) {
      $this->setDefaults([$field_name => Civi::settings()->get($field_name)]);
    }

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    // store settings
    foreach (self::SETTINGS as $field_name) {
      Civi::settings()->set($field_name, CRM_Utils_Array::value($field_name, $values));
    }

    // add user message
    CRM_Core_Session::setStatus(E::ts('Configuration Saved'), E::ts('Saved'), 'info');

    parent::postProcess();
  }

  /**
   * Get a list of event types
   *
   * @return array
   *   event type ID => name
   */
  public static function getEventTypes() {
    $event_types = ['' => E::ts('all')];
    $query = civicrm_api3(
        'OptionValue',
        'get',
        [
          'option_group_id' => 'event_type',
          'option.limit'    => 0,
          'is_active'       => 1,
          'return'          => 'value,label',
        ]
    );
    foreach ($query['values'] as $type) {
      $event_types[$type['value']] = $type['label'];
    }

    return $event_types;
  }

  /**
   * Get a list of eligible relationship types
   *
   * @return array
   *   relationship key => label
   */
  public static function getEventRelationshipTypes() {
    $relationship_types = [];

    // get one direction
    $type_ids_listed = [0];
    $query = civicrm_api3(
        'RelationshipType',
        'get',
        [
          'option.limit'    => 0,
          'contact_type_a'  => 'Individual',
          'is_active'       => 1,
          'return'          => 'id,name_a_b,name_b_a',
        ]
    );
    foreach ($query['values'] as $type) {
      $type_ids_listed[] = $type['id'];
      $relationship_types["{$type['id']}a"] = $type['name_a_b'];
    }

    // get one direction
    $query = civicrm_api3(
        'RelationshipType',
        'get',
        [
          'option.limit'    => 0,
          'contact_type_b'  => 'Individual',
          'id'              => ['NOT IN' => $type_ids_listed],
          'is_active'       => 1,
          'return'          => 'id,name_a_b,name_b_a',
        ]
    );
    foreach ($query['values'] as $type) {
      $relationship_types["{$type['id']}b"] = $type['name_b_a'];
    }

    return $relationship_types;
  }

  /**
   * Get a list of configured from email addresses
   *
   * @return array
   */
  public static function getFromEmailAddresses() {
    $from_addresses = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'from_email_address')
      ->execute()
      ->indexBy('name')
      ->column('label');

    return $from_addresses;
  }

  /**
   * Get a list of event registration status types
   *
   * @return array
   *   event type ID => name
   */
  public static function getParticipantStatusType() {
    $status_types = ['' => E::ts('all')];
    $status_types = ParticipantStatusType::get(FALSE)
      ->addSelect('id', 'label')
      ->execute()
      ->indexBy('id')
      ->column('label');
    return $status_types;
  }

  /**
   * Get a list of eligible custom fields
   *
   * @return array
   *   event type ID => name
   */
  protected function getEligibleContingentFields() {
    // first: get all custom groups with contacts
    $custom_groups = civicrm_api3(
        'CustomGroup',
        'get',
        [
          'option.limit'    => 0,
          'extends'         => ['IN' => ['Contact', 'Individual']],
          'is_active'       => 1,
          'sequential'      => 0,
          'return'          => 'id,title',
        ]
    );
    $eligible_custom_group_ids = array_keys($custom_groups['values']);

    // then: get all eligible fields
    $eligible_fields = [];
    $query = civicrm_api3(
        'CustomField',
        'get',
        [
          'option.limit'    => 0,
          'custom_group_id' => ['IN' => $eligible_custom_group_ids],
          'contact_type_b'  => 'Individual',
          'data_type'       => 'Int',
          'is_active'       => 1,
          'return'          => 'id,label,custom_group_id',
        ]
    );
    foreach ($query['values'] as $field) {
      $eligible_fields[$field['id']] = E::ts('%1 (%2)', [
        1 => $field['label'],
        2 => $custom_groups['values'][$field['custom_group_id']]['title'],
      ]);
    }

    return $eligible_fields;
  }

}
