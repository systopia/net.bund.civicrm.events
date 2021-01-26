<?php
/*-------------------------------------------------------+
| BUND Event Customisations                              |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
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


use CRM_Events_ExtensionUtil as E;
use Civi\RemoteEvent\Event\GetResultEvent as GetResultEvent;
use \Civi\RemoteParticipant\Event\ValidateEvent as ValidateEvent;

/**
 * RemoteEvent hooks (symfony events) implementation
 */
class CRM_Events_RemoteEventModifications
{
    /**
     * Apply the BUND registration restrictions to the event info
     *
     * @param GetResultEvent $result
     *   result event
     */
    public static function overrideRegistrationRestrictions(GetResultEvent $result)
    {
        $contact_id = $result->getRemoteContactID();
        $event_list = &$result->getEventData();
        if ($contact_id) {
            foreach ($event_list as &$event) {
                if (CRM_Events_Logic::shouldApplyRegistrationRestrictions($event)) {
                    // our restrictions apply here:
                    if (!empty($event['can_register'])) {
                        // registration for this contact is currently allowed,
                        //  let's see if we need to interfere
                        $contact_has_contingent_left = CRM_Events_Logic::contactStillHasContingentLeftForEvent($contact_id, $event);
                        if (!$contact_has_contingent_left) {
                            $result->logMessage("BUNDEvent: contact [{$contact_id}] does not have an event contingent any more");
                            $event['can_register'] = 0;
                        }

                        $contact_has_relationship = CRM_Events_Logic::contactHasRelationship($contact_id, $event['id']);
                        if (!$contact_has_relationship) {
                            $result->logMessage("BUNDEvent: contact [{$contact_id}] does not have the required relationship");
                            $event['can_register'] = 0;
                        }
                    }
                }
            }

        } else {
            // todo: contact not known => really disable registration for all events?
            $result->logMessage("BUNDEvent: contact [{$contact_id}] does not have the required relationship");
            foreach ($event_list as &$event) {
                $event['can_register'] = 0;
            }
        }
    }

    /**
     * Make sure that the registration is allowed under the BUND restrictions
     *
     * @param ValidateEvent $validation
     *   result event
     */
    public static function validateRegistrationRestrictions($validation)
    {
        $contact_id = $validation->getRemoteContactID();
        $event_id = $validation->getEventID();
        if (!CRM_Events_Logic::contactStillHasContingentLeftForEvent($contact_id, $event_id)) {
            $validation->addValidationError('remote_contact_id', E::ts("Contact has not enough contingent to register to this event."));
        }
        if (!CRM_Events_Logic::contactHasRelationship($contact_id, $event_id)) {
            $validation->addValidationError('remote_contact_id', E::ts("Contact doesn't have the required relationships to register to this event."));
        }
    }
}
