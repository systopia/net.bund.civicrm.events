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

namespace Civi\Events;

use Civi;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Participant;
use Civi\Api4\Event;
use Civi\Core\Service\AutoSubscriber;
use Civi\Core\Event\GenericHookEvent;
use CRM_Core_BAO_MessageTemplate;
use CRM_Utils_Mail;

class ParticipantSubscriber extends AutoSubscriber {

  /**
   * @return callable[]
   */
  public static function getSubscribedEvents(): array {
    return [
      'hook_civicrm_post::Participant' => 'sendBundWorkflowMessages',
    ];
  }

  /**
   * @param \Civi\Core\Event\GenericHookEvent $hookEvent
   *
   * @return void
   */
  public function sendBundWorkflowMessages(GenericHookEvent $hookEvent): void {
    $this->checkParticipationOverlaps($hookEvent);
    $this->checkParticipationU18($hookEvent);
  }

  /**
   * @param \Civi\Core\Event\GenericHookEvent $hookEvent
   *
   * @return void
   */
  public function checkParticipationOverlaps(GenericHookEvent $hookEvent): void {
    if ($hookEvent->action === 'create') {
      $registrations = Participant::get(FALSE)
        ->addSelect('event_id')
        ->addWhere('contact_id', '=', $hookEvent->object->contact_id)
        ->execute()
        ->column('event_id');
      // if participant attends more than one event: sort event dates
      if (count($registrations) > 1) {
        $events = Event::get(FALSE)
          ->addSelect('id', 'title', 'start_date', 'end_date')
          ->addWhere('id', 'IN', $registrations)
          ->addWhere('is_active', '=', TRUE)
          ->execute()
          ->indexBy('id');
        $eventsByDate = [];
        foreach ($events as $event) {
          $eventsByDate[$event['start_date'] . ' aaa ' . $event['id']] = $event['id'];
          $endDate = $event['end_date'];
          if ($endDate === NULL) {
            $endDate = (new \DateTime($event['start_date']))->setTime(23, 59, 59);
            $endDate = $endDate->format('Y-m-d H:i:s');
          }
          $eventsByDate[$endDate . ' zzz ' . $event['id']] = $event['id'];
        }
        ksort($eventsByDate);
        // check for overlapping dates
        $overlap = FALSE;
        $currentEventId = NULL;
        $overlappingEvents = [];
        foreach ($eventsByDate as $eventId) {
          if ($currentEventId === NULL) {
            $currentEventId = $eventId;
          }
          elseif ($currentEventId == $eventId) {
            $currentEventId = NULL;
          }
          else {
            $overlap = TRUE;
            $overlappingEvents[] = $currentEventId;
            $overlappingEvents[] = $eventId;
            break;
          }
        }
        if ($overlap) {
          $recipient_ids = Civi::settings()->get('bund_event_recipient_ids');
          $recipient_ids = explode(',', trim(str_replace(', ', ',', $recipient_ids)));
          if (is_array($recipient_ids) && !empty($recipient_ids)) {
            $recipient_bund_emails = Email::get(FALSE)
              ->addSelect('email')
              ->addWhere('contact_id', 'IN', $recipient_ids)
              ->addWhere('is_primary', '=', TRUE)
              ->execute()
              ->column('email');
            $from = Civi::settings()->get('bund_event_from_email_address');
            $count = count($recipient_ids);
            for ($i = 0; $i < $count; $i++) {
              CRM_Core_BAO_MessageTemplate::sendTemplate(
                [
                  'workflow' => 'participant_overlap_bund',
                  'contactId' => $recipient_ids[$i],
                  'tokenContext' => ['contactId' => $hookEvent->object->contact_id],
                  'tplParams' => [
                    'eventOneTitle' => $events[$overlappingEvents[0]]['title'],
                    'eventOneStart' => $events[$overlappingEvents[0]]['start_date'],
                    'eventOneEnd' => $events[$overlappingEvents[0]]['end_date'],
                    'eventTwoTitle' => $events[$overlappingEvents[1]]['title'],
                    'eventTwoStart' => $events[$overlappingEvents[1]]['start_date'],
                    'eventTwoEnd' => $events[$overlappingEvents[1]]['end_date'],
                  ],
                  'from' => CRM_Utils_Mail::formatFromAddress(Civi::settings()->get('bund_event_from_email_address')),
                  'toEmail' => $recipient_bund_emails[$i],
                ]
              );
            }
          }
        }
      }
    }
  }

  /**
   * @param \Civi\Core\Event\GenericHookEvent $hookEvent
   *
   * @return void
   */
  public function checkParticipationU18(GenericHookEvent $hookEvent): void {
    if ($hookEvent->action === 'create') {
      $age = Contact::get(FALSE)
        ->addSelect('age_years')
        ->addWhere('id', '=', $hookEvent->object->contact_id)
        ->execute()
        ->single()['age_years'] ?? NULL;
      $last_event_id = Participant::get(FALSE)
        ->addSelect('event_id')
        ->addWhere('contact_id', '=', $hookEvent->object->contact_id)
        ->addOrderBy('id', 'DESC')
        ->setLimit(1)
        ->execute()
        ->column('event_id')[0] ?? NULL;
      $event = Event::get(FALSE)
        ->addSelect('title', 'start_date', 'end_date', 'seminar_zusatzinfo.seminar_nur_f_r_vollj_hrige')
        ->addWhere('id', '=', $last_event_id)
        ->execute()
        ->single();
      if (isset($age) && $age < 18 && $event['seminar_zusatzinfo.seminar_nur_f_r_vollj_hrige']) {
        $recipient_ids = Civi::settings()->get('bund_event_recipient_ids');
        $recipient_ids = explode(',', trim(str_replace(', ', ',', $recipient_ids)));
        if (is_array($recipient_ids) && !empty($recipient_ids)) {
          $recipient_bund_emails = Email::get(FALSE)
            ->addSelect('email')
            ->addWhere('contact_id', 'IN', $recipient_ids)
            ->addWhere('is_primary', '=', TRUE)
            ->execute()
            ->column('email');
          $from = Civi::settings()->get('bund_event_from_email_address');
          $count = count($recipient_ids);
          for ($i = 0; $i < $count; $i++) {
            CRM_Core_BAO_MessageTemplate::sendTemplate(
              [
                'workflow' => 'participant_u18_bund',
                'contactId' => $recipient_ids[$i],
                'tokenContext' => ['contactId' => $hookEvent->object->contact_id],
                'tplParams' => [
                  'eventTitle' => $event['title'],
                  'eventStart' => $event['start_date'],
                  'eventEnd' => $event['end_date'],
                ],
                'from' => CRM_Utils_Mail::formatFromAddress(Civi::settings()
                  ->get('bund_event_from_email_address')),
                'toEmail' => $recipient_bund_emails[$i],
              ]
            );
          }
        }
      }
    }
  }

}
