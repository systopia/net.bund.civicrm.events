<?php

use CRM_Events_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'einsatzstelle_zusatzinfo',
        'table_name' => 'civicrm_value_einsatzstelle_zusatzinfo',
        'title' => E::ts('Zusätzliche Informationen zur Einsatzstelle'),
        'extends' => 'Organization',
        'extends_entity_column_value' => [
          'Einsatzstelle',
        ],
        'style' => 'Inline',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_einsaztstellennummer',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_einsaztstellennummer',
        'label' => E::ts('EST-Nummer'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_einsaztstellennummer',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_rechtstr_ger',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_rechtstr_ger',
        'label' => E::ts('RTR-Name'),
        'data_type' => 'ContactReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_rechtstr_ger',
        'filter' => 'action=lookup&group=5',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_rechtstr_gernummer',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_rechtstr_gernummer',
        'label' => E::ts('RTR-Nummer'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_rechtstr_gernummer',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_abrechnungsstellennummer',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_abrechnungsstellennummer',
        'label' => E::ts('AST-Nummer TG'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_abrechnungsstellennummer',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_ast_nummer_bp',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_ast_nummer_bp',
        'label' => E::ts('AST-Nummer BP'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_ast_nummer_bp',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_Debitorennummer',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'Debitorennummer',
        'label' => E::ts('Debitorennummer'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'debitorennummer',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_regionalstellen',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'regionalstellen',
        'title' => E::ts('Regionalstellen'),
        'description' => E::ts('Regionalstellen'),
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_regionalstellen_OptionValue_regionalstelle_s_d',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'regionalstellen',
        'label' => E::ts('Süd'),
        'value' => '1',
        'name' => 'regionalstelle_s_d',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_regionalstellen_OptionValue_regionalstelle_ost',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'regionalstellen',
        'label' => E::ts('Ost'),
        'value' => '2',
        'name' => 'regionalstelle_ost',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_regionalstellen_OptionValue_regionalstelle_west',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'regionalstellen',
        'label' => E::ts('West'),
        'value' => '3',
        'name' => 'regionalstelle_west',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_regionalstellen_OptionValue_regionalstelle_tgd',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'regionalstellen',
        'label' => E::ts('TGD'),
        'value' => '4',
        'name' => 'regionalstelle_tgd',
        'is_default' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_regionalstelle',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_regionalstelle',
        'label' => E::ts('Regionalstelle'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_regionalstelle',
        'option_group_id.name' => 'regionalstellen',
        'serialize' => 1,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_regionalstellenbetreuerin',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_regionalstellenbetreuerin',
        'label' => E::ts('RS-betreuer*in'),
        'data_type' => 'ContactReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_regionalstellenbetreuerin',
        'filter' => 'action=lookup&group=2',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_einsatzstelle_extern_verband',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'einsatzstelle_extern_verband',
        'title' => E::ts('Einsatzstelle Extern / Verband'),
        'description' => E::ts('eigene Einsatzstellen und externe Einsatzstellen'),
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_einsatzstelle_extern_verband_OptionValue_extern',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'einsatzstelle_extern_verband',
        'label' => E::ts('Extern'),
        'value' => '1',
        'name' => 'extern',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_einsatzstelle_extern_verband_OptionValue_verband',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'einsatzstelle_extern_verband',
        'label' => E::ts('Verband'),
        'value' => '2',
        'name' => 'verband',
        'is_default' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_extern_verband',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_extern_verband',
        'label' => E::ts('Einsatzstelle Extern / Verband'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_extern_verband',
        'option_group_id.name' => 'einsatzstelle_extern_verband',
        'serialize' => 1,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_einsatzstelle_seit',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_einsatzstelle_seit',
        'label' => E::ts('Einsatzstelle seit'),
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_searchable' => TRUE,
        'date_format' => 'yy-mm-dd',
        'column_name' => 'einsatzstelle_einsatzstelle_seit',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_pl_tze_insgesamt',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_pl_tze_insgesamt',
        'label' => E::ts('Plätze insgesamt'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_pl_tze_insgesamt',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_pl_tze_frei',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_pl_tze_frei',
        'label' => E::ts('freie Plätze'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'einsatzstelle_pl_tze_frei',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_einsatzstelle_zusatzinfo_CustomField_einsatzstelle_eingang_rahmenvereinbarung',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'einsatzstelle_zusatzinfo',
        'name' => 'einsatzstelle_eingang_rahmenvereinbarung',
        'label' => E::ts('Eingang RV'),
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_searchable' => TRUE,
        'date_format' => 'yy-mm-dd',
        'column_name' => 'einsatzstelle_eingang_rahmenvereinbarung',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
