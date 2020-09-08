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
        if ($contact_id) {
            $event_list = $result->getEventData();
            foreach ($event_list as &$event) {
                if (self::applyRegistrationRestrictions($event)) {
                    // our restrictions apply here:
                    if (!empty($event['can_register'])) {
                        // registration for this contact is currently allowed,
                        //  let's see if we need to interfere

                        $contact_has_contingent = self::contactStillHasContingent($contact_id);
                        if (!$contact_has_contingent) {
                            $result->logMessage("BUNDEvent: contact [{$contact_id}] does not have an event contingent any more");
                            $event['can_register'] = 0;
                        }

                        $contact_has_relationship = self::contactHasRelationship($contact_id);
                        if (!$contact_has_relationship) {
                            $result->logMessage("BUNDEvent: contact [{$contact_id}] does not have the required relationship");
                            $event['can_register'] = 0;
                        }
                    }
                }
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
        if (!self::contactStillHasContingent($contact_id)) {
            $validation->addError('remote_contact_id', E::ts("Contact has no more contingent to register to this event."));
        }
        if (!self::contactHasRelationship($contact_id)) {
            $validation->addError('remote_contact_id', E::ts("Contact doesn't have the required relationships to register to this event."));
        }
    }
}
