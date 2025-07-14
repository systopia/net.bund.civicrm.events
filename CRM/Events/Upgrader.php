<?php
/*-------------------------------------------------------+
| BUND Event Customisations                              |
| Copyright (C) 2023 SYSTOPIA                            |
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

use Civi\Api4\MessageTemplate;
use CRM_Events_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Events_Upgrader extends CRM_Extension_Upgrader_Base {

  public function enable(): void {
    // Create workflow message for overlapping event registrations
    $overlap_text = file_get_contents(E::path('templates/Events/MessageTemplates/overlapping_events.txt'));
    $overlap_html = file_get_contents(E::path('templates/Events/MessageTemplates/overlapping_events.html'));
    $overlap_tpl = [
      'workflow_name' => 'participant_overlap_bund',
      'msg_title' => 'Events - Overlap notification for BUND events',
      'msg_subject' => 'Parallelbuchung BFD-Seminare',
      'msg_text' => $overlap_text,
      'msg_html' => $overlap_html,
    ];
    // Create a "reserved" template. This is a pristine copy provided for reference.
    civicrm_api4('MessageTemplate', 'create',
      [
        'values' => $overlap_tpl + ['is_reserved' => 1, 'is_default' => 0],
        'checkPermissions' => FALSE,
      ]);
    // Create a default template. This is live. The administrator may edit/customize.
    civicrm_api4('MessageTemplate', 'create',
      [
        'values' => $overlap_tpl + ['is_reserved' => 0, 'is_default' => 1],
        'checkPermissions' => FALSE,
      ]);

    // Create workflow message for event registration by people under 18 years
    $u18_text = file_get_contents(E::path('templates/Events/MessageTemplates/u18_events.txt'));
    $u18_html = file_get_contents(E::path('templates/Events/MessageTemplates/u18_events.html'));
    $u18_tpl = [
      'workflow_name' => 'participant_u18_bund',
      'msg_title' => 'Events - U18 notification for BUND events',
      'msg_subject' => 'Anmeldung zu Ü18-BFD-Seminar durch Minderjährige*n',
      'msg_text' => $u18_text,
      'msg_html' => $u18_html,
    ];

    // Create a "reserved" template. This is a pristine copy provided for reference.
    civicrm_api4('MessageTemplate', 'create',
            [
              'values' => $u18_tpl + ['is_reserved' => 1, 'is_default' => 0],
              'checkPermissions' => FALSE,
            ]);

    // Create a default template. This is live. The administrator may edit/customize.
    civicrm_api4('MessageTemplate', 'create',
            [
              'values' => $u18_tpl + ['is_reserved' => 0, 'is_default' => 1],
              'checkPermissions' => FALSE,
            ]);
  }

  public function disable(): void {
    MessageTemplate::delete(FALSE)
      ->addWhere('workflow_name', '=', 'participant_overlap_bund')
      ->execute();
  }

}
