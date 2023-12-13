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

use Civi\Api4\Participant;
use Civi\Api4\Event;
use Civi\Core\Service\AutoSubscriber;
use Civi\Core\Event\GenericHookEvent;

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
    if($e->action === "create") {
      $registrations = Participant::get(FALSE)
              ->addWhere('contact_id', '=', $e->object->contact_id)
              ->execute();
      // if participant attends more than one event: sort event dates
      if($registrations->rowCount > 1) {
        $eventsByDate = array();
        foreach ($registrations as $registration) {
          $event = Event::get(FALSE)
              ->addWhere('id', '=', $registration["event_id"])
              ->execute()
              ->single();
          if($event["is_active"]){
            $eventsByDate[$event["start_date"] . " aaa " . $event["id"]] = $event["id"];
            $end_date = $event["end_date"];
            if($end_date === null){
              $end_date = substr($event["start_date"], 0, 10) . " 23:59:59";
            }
            $eventsByDate[$end_date . " zzz " . $event["id"]] = $event["id"];
          }
        }
        ksort($eventsByDate);
        // check for overlapping dates
        $overlap=false;
        $currentEventId=null;
        foreach($eventsByDate as $event_id){
          if($currentEventId === null){
            $currentEventId = $event_id;
          }
          else if($currentEventId == $event_id){
            $currentEventId = null;
          }
          else{
            $overlap=true;
            break;
          }
        }
        if($overlap){
          //send email to a person
        }
      }
    }
  }

}