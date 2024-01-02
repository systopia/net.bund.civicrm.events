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

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Participant;
use Civi\Api4\Event;
use Civi\Core\Service\AutoSubscriber;
use Civi\Core\Event\GenericHookEvent;
use CRM_Core_BAO_MessageTemplate;

// TODO: Since when does the class AutoSubscriber exist? Modify minimum CiviCRM Version accordingly

class ParticipantSubscriber extends AutoSubscriber {
  /**
   * The id of the contact that receives notifications via email.
   * This should be a person working at BUND. Currently: Felix Schwalbe.
   */
  private int $recipient_bund_id = 2886;

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
        ->addSelect('event_id')
        ->addWhere('contact_id', '=', $e->object->contact_id)
        ->execute()
        ->column('event_id');
      // if participant attends more than one event: sort event dates
      if (count($registrations) > 1) {
        $events = Event::get(FALSE)
          ->addSelect('id', 'title', 'start_date', 'end_date')
          ->addWhere('id', 'IN', $registrations)
          ->addWhere('is_active', '=', true)
          ->execute()
          ->indexBy('id');
        $eventsByDate = array();
        foreach ($events as $event) {
            $eventsByDate[$event["start_date"] . " aaa " . $event["id"]] = $event["id"];
            $end_date = $event["end_date"];
            if ($end_date === null) {
              $end_date = substr($event["start_date"], 0, 10) . " 23:59:59";
            }
            $eventsByDate[$end_date . " zzz " . $event["id"]] = $event["id"];
        }
        ksort($eventsByDate);
        // check for overlapping dates
        $overlap = false;
        $currentEventId = null;
        $overlappingEvents = array();
        foreach ($eventsByDate as $eventId) {
          if ($currentEventId === null) {
            $currentEventId = $eventId;
          } else if ($currentEventId == $eventId) {
            $currentEventId = null;
          } else {
            $overlap = true;
            $overlappingEvents[] = $currentEventId;
            $overlappingEvents[] = $eventId;
            break;
          }
        }
        if ($overlap) {

          $recipient_bund_email = Email::get(FALSE)
            ->addWhere('contact_id', '=', $this->recipient_bund_id)
            ->addWhere('is_primary', '=', TRUE)
            ->execute()
            ->single();

          CRM_Core_BAO_MessageTemplate::sendTemplate(
            [
              'workflow' => 'participant_overlap_bund',
              'contactId' => $this->recipient_bund_id,
              'tokenContext' => ['contactId' => $e->object->contact_id],
              'tplParams' => [
                      'eventOneTitle' => $events[$overlappingEvents[0]]['title'],
                      'eventOneStart' => $events[$overlappingEvents[0]]['start_date'],
                      'eventOneEnd' => $events[$overlappingEvents[0]]['end_date'],
                      'eventTwoTitle' => $events[$overlappingEvents[1]]['title'],
                      'eventTwoStart' => $events[$overlappingEvents[1]]['start_date'],
                      'eventTwoEnd' => $events[$overlappingEvents[1]]['end_date']
              ],
              'from' => 'bundesfreiwilligendienst@bund.net',
              'toEmail' => $recipient_bund_email['email'],
            ]
          );
        }
      }
    }
  }

}