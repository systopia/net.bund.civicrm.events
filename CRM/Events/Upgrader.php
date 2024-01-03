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

use Civi\Api4\MessageTemplate;
use CRM_Events_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Events_Upgrader extends CRM_Extension_Upgrader_Base
{

    /**
     * Installer
     */
    public function install()
    {
    }

    public function enable(){

      $overlap_text = 'Hallo Seminarverwaltung,

ein/e Seminarportal-User*in hat versehentlich zwei parallel stattfindende BFD-Seminare gebucht. Bitte überprüfe die Buchungen.

Freiwillige*r: {contact.display_name}

Seminar 1: {$eventOneTitle}, {$eventOneStart|crmDate:"Datetime"} - {$eventOneEnd|crmDate:"Datetime"}
Seminar 2: {$eventTwoTitle}, {$eventTwoStart|crmDate:"Datetime"} - {$eventTwoEnd|crmDate:"Datetime"}

Link zum CiviCRM-Kontakt: {domain.base_url}civicrm/contact/view?reset=1&cid={contact.id}

Dies ist eine automatisch generierte Nachricht.';

      $overlap_html = '<p>Hallo Seminarverwaltung,</p>

<p>ein/e Seminarportal-User*in hat versehentlich zwei parallel stattfindende BFD-Seminare gebucht. Bitte überprüfe die Buchungen.</p>

<p>Freiwillige*r: <a href="{domain.base_url}civicrm/contact/view?reset=?cid={contact.id}">{contact.display_name}</a></p>

<p>Seminar 1: {$eventOneTitle}, {$eventOneStart|crmDate:"Datetime"} - {$eventOneEnd|crmDate:"Datetime"}<br />
Seminar 2: {$eventTwoTitle}, {$eventTwoStart|crmDate:"Datetime"} - {$eventTwoEnd|crmDate:"Datetime"}</p>

<p>Dies ist eine automatisch generierte Nachricht.</p>';

      $overlap_tpl = [
        'workflow_name' => 'participant_overlap_bund',
        'msg_title' => 'Events - Overlap notification for BUND events',
        'msg_subject' => 'Parallelbuchung BFD-Seminare',
        'msg_text' =>   $overlap_text,
        'msg_html' => $overlap_html
      ];

        // Create a "reserved" template. This is a pristine copy provided for reference.
        civicrm_api4('MessageTemplate', 'create',
                ['values' => $overlap_tpl + ['is_reserved' => 1, 'is_default' => 0],
                ]);

        // Create a default template. This is live. The administrator may edit/customize.
        civicrm_api4('MessageTemplate', 'create',
                ['values' => $overlap_tpl + ['is_reserved' => 0, 'is_default' => 1],
                ]);
    }

    public function disable(){
      MessageTemplate::delete(TRUE)
              ->addWhere('workflow_name', '=', 'participant_overlap_bund')
              ->execute();
    }
}
