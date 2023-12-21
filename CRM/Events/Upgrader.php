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

      $overlap_text = '{contact.email_greeting_display},

du hast dich gerade für eine Veranstaltung des BUND angemeldet.
Diese Veranstaltung überschneidet sich zeitlich mit anderen Veranstaltungen,
für die du registriert bist. Bitte logge dich ein unter .... und prüfe deine Veranstaltungsteilnahmen.

Viele Grüße, das Team vom BUND';

      $overlap_html = '<p>{contact.email_greeting_display},</p>

      <p>du hast dich gerade für eine Veranstaltung des BUND angemeldet.
      Diese Veranstaltung überschneidet sich zeitlich mit anderen Veranstaltungen,
      für die du registriert bist. Bitte logge dich ein unter .... und prüfe deine Veranstaltungsteilnahmen.</p>

      <p>Viele Grüße,<br />das Team vom BUND</p>';


      $overlap_tpl = [
        'workflow_name' => 'participant_overlap_bund',
        'msg_title' => 'Events - Overlap notification for BUND participants',
        'msg_subject' => 'Deine BUND-Seminartage überlappen sich',
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
