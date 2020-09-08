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

/**
 * RemoteEvent hooks (symfony events) implementation
 */
class CRM_Events_Logic
{
    /**
     * Check if the event registration restrictions should be
     *   applied to the given event
     *
     * @param array $event_data
     *   event data, in particular containing event_type / id
     *
     * @return bool
     *   should the restriction be applied?
     */
    public static function shouldApplyRegistrationRestrictions($event_data)
    {
        $event_types = Civi::settings()->get('bund_event_types');
        if (is_array($event_types) && !empty($event_types)) {
            // we look for certain event types
            if (empty($event_data['event_type_id'])) {
                $event_data['event_type_id'] = civicrm_api3('Event', 'getsingle', [
                    'id' => $event_data['id'],
                    'return' => 'event_type_id'
                ]);
            }

            // return true if this is one of our event types
            return in_array($event_data['event_type_id'], $event_types);
        }

        // not configured correctly
        return false;
    }

    /**
     * Check if the total contingent has not been exceeded
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return boolean
     *   is there still some contingent left?
     */
    public static function contactStillHasContingent($contact_id)
    {
        $event_contingent_granted = self::getContactEventContingentGranted($contact_id);
        $event_contingent_used = self::getContactEventContingentUsed($contact_id);
        return $event_contingent_used < $event_contingent_granted;
    }

    /**
     * Return if the contact has one of the relationships
     *  required to participate in the events
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return integer
     *   number of days granted to the contact
     */
    public static function contactHasRelationship($contact_id)
    {
        // todo: implement
        return true;
    }

    /**
     * Return the total event contingent the contact has (in days)
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return integer
     *   number of days granted to the contact
     */
    public static function getContactEventContingentGranted($contact_id)
    {
        $contact_id = (int) $contact_id;
        if ($contact_id) {
            $granted_field_id = (int) Civi::settings()->get('bund_event_contingent_field');
            if ($granted_field_id) {
                return (int) civicrm_api3('Contact', 'getvalue', [
                    'id'     => $contact_id,
                    'return' => "custom_{$granted_field_id}"]);
            } else {
                // no field ID set
            }
        } else {
            // no contact ID given
        }
        return 0;
    }

    /**
     * Return the total sum of days used in registrations
     *   for events with the required types
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return integer
     *   number of days used by the contact
     *
     * @todo consider roles? consider multiple participants per contact&event?
     */
    public static function getContactEventContingentUsed($contact_id)
    {
        $contact_id = (int) $contact_id;
        if ($contact_id) {
            // check if we restrict to certain event types
            $AND_HAS_THE_RIGHT_EVENT_TYPE = 'AND TRUE';
            $event_types = Civi::settings()->get('bund_event_types');
            if (is_array($event_types) && !empty($event_types)) {
                $event_type_list = implode(',', array_map('intval', $event_types));
                $AND_HAS_THE_RIGHT_EVENT_TYPE = "AND event.event_type_id IN ({$event_type_list})";
            }

            // build query
            $query = "
                SELECT SUM(
                    IF(event.end_date IS NOT NULL, DATEDIFF(event.end_date, event.start_date), 0) + 1
                ) AS total_days
                FROM civicrm_participant participant
                LEFT JOIN civicrm_event  event
                       ON event.id = participant.event_id
                LEFT JOIN civicrm_participant_status_type status_type
                       ON status_type.id = participant.status_id 
                WHERE participant.contact_id = {$contact_id}
                  AND status_type.class IN ('Positive', 'Pending')
                  {$AND_HAS_THE_RIGHT_EVENT_TYPE}";
            return (int) CRM_Core_DAO::singleValueQuery($query);
        } else {
            // no contact ID given
        }
        return 0;
    }
}
