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

namespace Civi\Events;

use Civi\Api4\Email;
use Civi\Api4\Participant;
use Civi\Api4\Event;
use Civi\Core\Service\AutoSubscriber;
use Civi\Core\Event\GenericHookEvent;
use CRM_Core_BAO_MessageTemplate;

// TODO: Since when does the class AutoSubscriber exist? Modify minimum CiviCRM Version accordingly

class ParticipantSubscriber extends AutoSubscriber {

  /**
   * @return callable[]
   */
  public static function getSubscribedEvents(): array {
    return [
            'hook_civicrm_post::Participant' => 'checkParticipationOverlaps'
    ];
  }

  /**
   * @param GenericHookEvent $e
   * @return void
   */
  public function checkParticipationOverlaps(GenericHookEvent $e): void {
    if ($e->action === "create") {
      $registrations = Participant::get(FALSE)
              ->addWhere('contact_id', '=', $e->object->contact_id)
              ->execute();
      // if participant attends more than one event: sort event dates
      if ($registrations->rowCount > 1) {
        $eventsByDate = array();
        foreach ($registrations as $registration) {
          $event = Event::get(FALSE)
              ->addWhere('id', '=', $registration["event_id"])
              ->execute()
              ->single();
          if ($event["is_active"]) {
            $eventsByDate[$event["start_date"] . " aaa " . $event["id"]] = $event["id"];
            $end_date = $event["end_date"];
            if ($end_date === null) {
              $end_date = substr($event["start_date"], 0, 10) . " 23:59:59";
            }
            $eventsByDate[$end_date . " zzz " . $event["id"]] = $event["id"];
          }
        }
        ksort($eventsByDate);
        // check for overlapping dates
        $overlap = false;
        $currentEventId = null;
        foreach ($eventsByDate as $event_id) {
          if ($currentEventId === null) {
            $currentEventId = $event_id;
          } else if ($currentEventId == $event_id) {
            $currentEventId = null;
          } else {
            $overlap = true;
            break;
          }
        }
        if ($overlap) {

          $participantEmail = Email::get(FALSE)
            ->addWhere('contact_id', '=', $e->object->contact_id)
            ->addWhere('is_primary', '=', TRUE)
            ->execute()
            ->single();

          //option to add parameters if the message template shall contain more specific information
          $tplParams = NULL;

          CRM_Core_BAO_MessageTemplate::sendTemplate(
            [
              'workflow' => 'participant_overlap_bund',
              'contactId' => $e->object->contact_id,
              'tplParams' => $tplParams,
              //TODO: add from email address
              'from' => 'jana@systopia.de',
              'toEmail' => $participantEmail['email'],
            ]
          );
        }
      }
    }
  }

}