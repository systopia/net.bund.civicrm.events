<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_teilnehmer_zusatzinfo',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'teilnehmer_zusatzinfo',
        'table_name' => 'civicrm_value_teilnehmer_zusatzinfo',
        'title' => E::ts('Zusätzliche Informationen für Seminar-Teilnehmer'),
        'extends' => 'Participant',
        'style' => 'Inline',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_teilnehmer_zusatzinfo_CustomField_teilnehmer_grund_der_abwesenheit',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'teilnehmer_zusatzinfo',
        'name' => 'teilnehmer_grund_der_abwesenheit',
        'label' => E::ts('Grund der Abwesenheit'),
        'data_type' => 'Memo',
        'html_type' => 'TextArea',
        'is_searchable' => TRUE,
        'attributes' => 'rows=4, cols=60',
        'column_name' => 'teilnehmer_grund_der_abwesenheit',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_teilnehmer_zusatzinfo_CustomField_teilnehmer_gesamttage_anmeldung',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'teilnehmer_zusatzinfo',
        'name' => 'teilnehmer_gesamttage_anmeldung',
        'label' => E::ts('Gesamttage Anmeldung'),
        'data_type' => 'Float',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'teilnehmer_gesamttage_anmeldung',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_teilnehmer_zusatzinfo_CustomField_teilnehmer_fehltage_entschuldigt',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'teilnehmer_zusatzinfo',
        'name' => 'teilnehmer_fehltage_entschuldigt',
        'label' => E::ts('Fehltage entschuldigt'),
        'data_type' => 'Float',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'help_pre' => E::ts('Bei Eintragungen im Feld "entschuldigt" sind die Zahl der eingetragenen Seminartage nicht mehr verfügbar für andere Seminare'),
        'column_name' => 'teilnehmer_fehltage_entschuldigt',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_teilnehmer_zusatzinfo_CustomField_teilnehmer_fehltage_unentschuldigt',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'teilnehmer_zusatzinfo',
        'name' => 'teilnehmer_fehltage_unentschuldigt',
        'label' => E::ts('Fehltage unentschuldigt'),
        'data_type' => 'Float',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'help_pre' => E::ts('Bei Eintragungen im Feld "unentschuldigt" werden die Zahl der eingetragenen Seminartage wieder verfügbar für andere Seminare'),
        'column_name' => 'teilnehmer_fehltage_unentschuldigt',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_teilnehmer_zusatzinfo_CustomField_teilnehmer_spesen',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'teilnehmer_zusatzinfo',
        'name' => 'teilnehmer_spesen',
        'label' => E::ts('TN-Spesen'),
        'data_type' => 'Money',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'teilnehmer_spesen',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
