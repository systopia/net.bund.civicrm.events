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
use Civi\Core\Service\AutoSubscriber;
use Civi\Core\Event\GenericHookEvent;

// TODO: Since when does the class AutoSubscriber exist? Modify minimum CiviCRM Version accordingly

class ParticipantSubscriber extends AutoSubscriber {

  /**
   * @return callable[]
   */
  public static function getSubscribedEvents(): array {
    return ['hook_civicrm_post' => 'checkParticipationOverlaps'];
  }

  /**
   * @param GenericHookEvent $e
   * @return void
   */
  public function checkParticipationOverlaps(GenericHookEvent $e): void {
    $events = Participant::get()
            ->addWhere('contact_id', '=', 5)
            ->execute();
    foreach ($events as $event) {
      // do something
    }
  }

}