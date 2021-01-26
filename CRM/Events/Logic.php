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
    const EVENT_DAYS_GRANTED = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_pflicht';
    const EVENT_DAYS_BOOKED  = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_gebucht';
    const EVENT_DAYS_USED    = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_geleistet';
    const EVENT_DAYS_LEFT    = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_offen';
    const EVENT_DAYS_MISSED  = 'freiwillige_zusatzinfos.freiwillige_gesamtfehltage_entschuldigt';
    const EVENT_DAYS_FAILED  = 'freiwillige_zusatzinfos.freiwillige_gesamtfehltage_unentschuldigt';

    // currently not used, relationship(s) defined via settings, using is_active flag at relationship
    //    const RELATIONSHIP_NAME         = 'ist Freiwillige* bei';
    //    const RELATIONSHIP_GROUP_NAME   = 'einsatz_zusatzinfos';
    //    const RELATIONSHIP_STATUS_FIELD = 'einsatz_status';
    //    const RELATIONSHIP_STATUS_OK    = [1,2];


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
        if (empty($event_types)) {
            return true;
        }

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
     *   contact that wants to register
     *
     * @param array $event
     *   event data
     *
     * @return boolean
     *   is there still some contingent left?
     */
    public static function contactStillHasContingentLeftForEvent($contact_id, $event)
    {
        try {
            $event_contingent_left = self::getContactEventContingentLeft($contact_id);
            $event_days = self::getEventDays($event);
            return $event_contingent_left >= $event_days;
        } catch (CiviCRM_API3_Exception $ex) {
            Civi::log()->debug("Error in contactStillHasContingentLeftForEvent, contact ID {$contact_id}: " . $ex->getMessage());
        }
    }

    /**
     * Return if the contact has one of the relationships
     *  required to participate in the events
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @param integer $event_id
     *   event ID
     *
     * @return integer
     *   number of days granted to the contact
     *
     * @deprecated
     */
    public static function contactHasRelationship($contact_id, $event_id)
    {
        $contact_id = (int) $contact_id;
        $event_id = (int) $event_id;
        $required_relationships = Civi::settings()->get('bund_event_relationship_types');
        if (empty($required_relationships) || !is_array($required_relationships)) {
            // no relationship set -> great!
            return true;
        }

        // build SQL query
        $relationships = $relationship_joins = [];
        foreach ($required_relationships as $relationship_spec) {
            if (preg_match('/^([0-9]+)([ab])$/', $relationship_spec, $matches)) {
                $relationship_type_id = (int) $matches[1];
                $relationship_direction = $matches[2];
                $relationship_joins[] = "
                    LEFT JOIN civicrm_relationship rel{$relationship_spec} 
                       ON contact.id = rel{$relationship_spec}.contact_id_{$relationship_direction} 
                      AND rel{$relationship_spec}.relationship_type_id = {$relationship_type_id}
                      AND rel{$relationship_spec}.is_active = 1
                      AND (  (rel{$relationship_spec}.start_date IS NULL)
                          OR (rel{$relationship_spec}.start_date < event.start_date)
                          )
                      AND (  (rel{$relationship_spec}.end_date IS NULL)
                          OR (rel{$relationship_spec}.end_date > event.start_date)
                          ) ";
                $relationships[] = "rel{$relationship_spec}.id";
            } else {
                 throw new Exception("Invalid relationship spec in 'bund_event_contingent_field': " . $relationship_spec);
            }
        }
        $JOIN_RELATIONSHIPS = implode("\n ", $relationship_joins);
        $VALID_RELATIONSHIPS = implode(',', $relationships);

        // final query: find (coalesce) all relationships
        $valid_relationship_query = "
            SELECT SUM(COALESCE({$VALID_RELATIONSHIPS})) AS valid_relationship
            FROM civicrm_contact contact
            LEFT JOIN civicrm_event event ON event.id = {$event_id}
            {$JOIN_RELATIONSHIPS}
            WHERE contact.id = {$contact_id}";
        $valid_relationship_count = (int) CRM_Core_DAO::singleValueQuery($valid_relationship_query);
        return $valid_relationship_count > 0;
    }

    /**
     * Calculate the remaining days left for the contact
     *
     * @param integer $contact_id
     *   the contact
     *
     * @return integer
     *   number of days left
     */
    public static function getContactEventContingentLeft($contact_id)
    {
        $contingent_data = CRM_Events_Logic::getContactEventContingentData($contact_id);
        // don't do this: $contingent_used = $contingent_data[self::EVENT_DAYS_BOOKED] + $contingent_data[self::EVENT_DAYS_USED];
        // calculate LIVE instead
        $contingent_used = self::getContactEventContingentUsed($contact_id);

        return $contingent_data[self::EVENT_DAYS_GRANTED] - $contingent_used;
    }

    /**
     * Get the number of days an event counts for
     *
     * @param array $event
     *   event data
     *
     * @return integer
     *   number of days
     */
    public static function getEventDays($event)
    {
        // if end_date is empty, it's a one-day affair
        if (empty($event['end_date'])) {
            return 1;
        }

        $start_date = date('Y-m-d', strtotime($event['start_date']));
        $end_date   = date('Y-m-d', strtotime($event['end_date']));
        $seconds_difference = strtotime($end_date) - strtotime($start_date);
        $days_difference = $seconds_difference / (60 * 60 * 24);
        return 1 + $days_difference;
    }


    /**
     * Get the contingent data from the contact
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return array
     *   contingent data. fields:
     *    contact_id
     *    EVENT_DAYS_GRANTED,
     *    EVENT_DAYS_BOOKED,
     *    EVENT_DAYS_USED,
     *    EVENT_DAYS_LEFT
     */
    protected static function getContactEventContingentData($contact_id, $cached = true) {
        // caching
        static $contact_event_contingent = [];
        $contact_id = (int) $contact_id;
        if (!$cached) {
            unset($contact_event_contingent[$contact_id]);
        }
        if (!empty($contact_event_contingent[$contact_id])) {
            return $contact_event_contingent[$contact_id];
        }

        // warm cache
        CRM_Events_CustomData::cacheCustomGroups(['freiwillige_zusatzinfos']);

        // create field list
        $return_fields = [
            self::EVENT_DAYS_GRANTED => 1,
            self::EVENT_DAYS_BOOKED => 1,
            self::EVENT_DAYS_USED => 1,
            self::EVENT_DAYS_LEFT => 1,
        ];
        CRM_Events_CustomData::resolveCustomFields($return_fields);
        $return_field_list = 'id,' . implode(',', array_keys($return_fields));

        // run the query
        $contingent_data = civicrm_api3('Contact', 'getsingle', [
            'id' => $contact_id,
            'return' => $return_field_list
        ]);


        // prep result
        CRM_Events_CustomData::labelCustomFields($contingent_data);
        $contingent_data['contact_id'] = $contingent_data['id'];
        $contingent_data[self::EVENT_DAYS_GRANTED] = CRM_Utils_Array::value(self::EVENT_DAYS_GRANTED, $contingent_data, 0);
        $contingent_data[self::EVENT_DAYS_USED]    = CRM_Utils_Array::value(self::EVENT_DAYS_USED,    $contingent_data, 0);
        $contingent_data[self::EVENT_DAYS_BOOKED]  = CRM_Utils_Array::value(self::EVENT_DAYS_BOOKED,  $contingent_data, 0);
        $contingent_data[self::EVENT_DAYS_LEFT]    = CRM_Utils_Array::value(self::EVENT_DAYS_LEFT,    $contingent_data, 0);

        // cache + return
        $contact_event_contingent[$contact_id] = $contingent_data;
        return $contingent_data;
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
