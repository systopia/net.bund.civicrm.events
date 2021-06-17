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
use \Civi\RemoteParticipant\Event\ChangingEvent;

/**
 * RemoteEvent hooks (symfony events) implementation
 */
class CRM_Events_Logic
{
    // participation obligation
    const EVENT_DAYS         = 'seminar_zusatzinfo.seminar_gesamtzahl_tage';
    const EVENT_DAYS_GRANTED = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_pflicht';
    const EVENT_DAYS_BOOKED  = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_gebucht';
    const EVENT_DAYS_USED    = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_geleistet';
    const EVENT_DAYS_LEFT    = 'freiwillige_zusatzinfos.freiwillige_seminar_tage_offen';

    // missed (with valid excuse) and skipped (no excuse) days
    const TOTAL_DAYS_MISSED  = 'freiwillige_zusatzinfos.freiwillige_gesamtfehltage_entschuldigt';
    const TOTAL_DAYS_SKIPPED = 'freiwillige_zusatzinfos.freiwillige_gesamtfehltage_unentschuldigt';
    const EVENT_DAYS_MISSED  = 'teilnehmer_zusatzinfo.teilnehmer_fehltage_entschuldigt';
    const EVENT_DAYS_SKIPPED = 'teilnehmer_zusatzinfo.teilnehmer_fehltage_unentschuldigt';

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
            Civi::log()->debug("Contact [{$contact_id}] needs {$event_days} day(s) and has {$event_contingent_left} day(s) left");
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
        if (empty($event['id'])) {
            return 0;
        }
        $event_id = (int) $event['id'];

        // cache results
        static $event_days = [];
        if (isset($event_days[$event_id])) {
            return $event_days[$event_id];
        }

        // load event, if necessary
        $days_override = CRM_Events_CustomData::getCustomFieldKey('seminar_zusatzinfo', 'seminar_gesamtzahl_tage');
        if (empty($event['start_date'])) {
            $event = civicrm_api3('Event', 'getsingle', [
                'id'     => $event_id,
                'return' => "start_date,end_date,id,{$days_override}"
            ]);
            CRM_Events_CustomData::labelCustomFields($event);
        }


        // start calculating
        $event_day_count = null;

        // if relevant event data loaded, just get this one value
        if (!isset($event['seminar_zusatzinfo.seminar_gesamtzahl_tage'])) {
            $custom_table = CRM_Events_CustomData::getGroupTable('seminar_zusatzinfo');
            $custom_field = CRM_Events_CustomData::getCustomField('seminar_zusatzinfo', 'seminar_gesamtzahl_tage');
            if ($custom_field && $custom_table) {
                $result = CRM_Core_DAO::singleValueQuery("
                    SELECT {$custom_field['column_name']}
                    FROM {$custom_table}
                    WHERE entity_id = {$event_id}");
                if ($result !== null) {
                    $event_day_count = (int) $result;
                }
            }
        }

        // if there is something in the custom field
        if ($event_day_count === null && !empty($event['seminar_zusatzinfo.seminar_gesamtzahl_tage'])) {
            $event_day_count = (int) $event['seminar_zusatzinfo.seminar_gesamtzahl_tage'];
            //Civi::log()->debug("custom: {$event_day_count}");
        }

        // if end_date is empty, it's a one-day affair
        if ($event_day_count === null) {
            // make sure the end date is loaded
            if (!isset($event['end_date'])) {
                $event['end_date'] = civicrm_api3('Event', 'getvalue', ['id' => $event_id, 'return' => 'end_date']);
            }
            if (empty($event['end_date'])) {
                $event_day_count = 1;
            }
        }

        // else calculate the 'temporal distance' in days and add one
        if ($event_day_count === null) {
            $start_date = date('Y-m-d', strtotime($event['start_date']));
            $end_date   = date('Y-m-d', strtotime($event['end_date']));
            $seconds_difference = strtotime($end_date) - strtotime($start_date);
            $days_difference = (int) ($seconds_difference / (60 * 60 * 24));
            $event_day_count = 1 + $days_difference;
        }

        // cache and return
        $event_days[$event_id] = $event_day_count;
        //Civi::log()->debug("Event [{$event_id}] has {$event_day_count} counts");
        return $event_day_count;
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
            self::TOTAL_DAYS_MISSED => 1,
            self::TOTAL_DAYS_SKIPPED => 1,
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
        $contingent_data[self::EVENT_DAYS_GRANTED] = (int) CRM_Utils_Array::value(self::EVENT_DAYS_GRANTED, $contingent_data, 0);
        $contingent_data[self::EVENT_DAYS_USED]    = (int) CRM_Utils_Array::value(self::EVENT_DAYS_USED,    $contingent_data, 0);
        $contingent_data[self::EVENT_DAYS_BOOKED]  = (int) CRM_Utils_Array::value(self::EVENT_DAYS_BOOKED,  $contingent_data, 0);
        $contingent_data[self::EVENT_DAYS_LEFT]    = (int) CRM_Utils_Array::value(self::EVENT_DAYS_LEFT,    $contingent_data, 0);

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
     * @param string $restrict
     *   can have the following values:
     *    'past': only counts events in the past
     *    'future': only counts events in the future, including today
     *    otherwise: all (eligible) events
     *
     * @return integer
     *   number of days used by the contact
     *
     * @todo consider roles? consider multiple participants per contact&event?
     */
    public static function getContactEventContingentUsed($contact_id, $restrict = null)
    {
        $contact_id = (int) $contact_id;
        $number_of_days = 0;

        if ($contact_id) {
            // check if we restrict to certain event types
            $HAS_THE_RIGHT_EVENT_TYPE = 'TRUE';
            $event_types = Civi::settings()->get('bund_event_types');
            if (is_array($event_types) && !empty($event_types)) {
                $event_type_list = implode(',', array_map('intval', $event_types));
                $HAS_THE_RIGHT_EVENT_TYPE = "event.event_type_id IN ({$event_type_list})";
            }

            // selecting for past events or upcoming ones?
            switch ($restrict) {
                case 'past':
                    $EVENT_SELECTOR = "DATE(event.start_date) < DATE(NOW())";
                    break;

                case 'future':
                    $EVENT_SELECTOR = "DATE(event.start_date) >= DATE(NOW())";
                    break;

                default:
                    $EVENT_SELECTOR = "TRUE";
                    break;

            }

            // build query
            $query = "
                SELECT GROUP_CONCAT(DISTINCT(event.id)) AS events
                FROM civicrm_participant participant
                LEFT JOIN civicrm_event  event
                       ON event.id = participant.event_id
                LEFT JOIN civicrm_participant_status_type status_type
                       ON status_type.id = participant.status_id 
                WHERE participant.contact_id = {$contact_id}
                  AND status_type.class IN ('Positive')
                  AND {$EVENT_SELECTOR}
                  AND {$HAS_THE_RIGHT_EVENT_TYPE}";
            $events = CRM_Core_DAO::singleValueQuery($query);
            foreach (explode(',', $events) as $event_id) {
                if ($event_id) {
                    $number_of_days += self::getEventDays(['id' => $event_id]);
                }
            }
        }
        return $number_of_days;
    }

    /**
     * Return the accumulated missed (with excuse) and skipped (w/o excuse) days,
     *  explicitly NOT checking the participant status.
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return array
     *   [missed_days, skipped_days]
     *
     * @see https://pws.bund.net/issues/4691
     */
    public static function getMissedDays($contact_id)
    {
        $contact_id = (int) $contact_id;
        if ($contact_id) {
            // check if we restrict to certain event types
            $HAS_THE_RIGHT_EVENT_TYPE = 'TRUE';
            $event_types = Civi::settings()->get('bund_event_types');
            if (is_array($event_types) && !empty($event_types)) {
                $event_type_list = implode(',', array_map('intval', $event_types));
                $HAS_THE_RIGHT_EVENT_TYPE = "event.event_type_id IN ({$event_type_list})";
            }

            // build query
            $custom_table = CRM_Events_CustomData::getGroupTable('teilnehmer_zusatzinfo');
            $days_missed  = CRM_Events_CustomData::getCustomField('teilnehmer_zusatzinfo', 'teilnehmer_fehltage_entschuldigt');
            $days_skipped = CRM_Events_CustomData::getCustomField('teilnehmer_zusatzinfo', 'teilnehmer_fehltage_unentschuldigt');
            $query = "
                SELECT 
                       SUM(participant_data.{$days_missed['column_name']})  AS days_missed,
                       SUM(participant_data.{$days_skipped['column_name']}) AS days_skipped
                FROM civicrm_participant participant
                LEFT JOIN {$custom_table} participant_data
                       ON participant_data.entity_id = participant.id
                LEFT JOIN civicrm_event  event
                       ON event.id = participant.event_id
                WHERE participant.contact_id = {$contact_id}
                  AND {$HAS_THE_RIGHT_EVENT_TYPE}
                GROUP BY participant.contact_id";
            $data = CRM_Core_DAO::executeQuery($query);
            $data->fetch();
            return [
                (isset($data->days_missed)  ? (int) $data->days_missed  : 0),
                (isset($data->days_skipped) ? (int) $data->days_skipped : 0),
            ];
        }
        return [0,0];
    }

    /**
     * Update a contact's event stats:
     *   EVENT_DAYS_BOOKED
     *   EVENT_DAYS_USED
     *   EVENT_DAYS_BOOKED
     *
     * @param integer $contact_id
     *   contact ID
     */
    public static function updateContactEventStats($contact_id)
    {
        $contact_id = (int) $contact_id;
        if (!$contact_id) return;

        $current_values = self::getContactEventContingentData($contact_id, false);
        $update = [];
        // check days used
        $days_used = self::getContactEventContingentUsed($contact_id, 'past');
        if ($current_values[self::EVENT_DAYS_USED] != $days_used) {
            $update[self::EVENT_DAYS_USED] = $days_used;
        }

        // check days booked
        $days_booked = self::getContactEventContingentUsed($contact_id, 'future');
        if ($current_values[self::EVENT_DAYS_BOOKED] != $days_booked) {
            $update[self::EVENT_DAYS_BOOKED] = $days_booked;
        }

        $days_left = $current_values[self::EVENT_DAYS_GRANTED] - $days_used - $days_booked;
        if ($current_values[self::EVENT_DAYS_LEFT] != $days_left) {
            $update[self::EVENT_DAYS_LEFT] = $days_left;
        }

        // check missed/skipped days (see BUND-4691)
        list($missed_days, $skipped_days) = self::getMissedDays($contact_id);
        if ($current_values[self::TOTAL_DAYS_MISSED] != $missed_days) {
            $update[self::TOTAL_DAYS_MISSED] = $missed_days;
        }
        if ($current_values[self::TOTAL_DAYS_SKIPPED] != $skipped_days) {
            $update[self::TOTAL_DAYS_SKIPPED] = $skipped_days;
        }

        // update if there is differences
        if (!empty($update)) {
            $update['id'] = $contact_id;
            CRM_Events_CustomData::resolveCustomFields($update);
            //Civi::log()->debug("update: " . json_encode($update));
            civicrm_api3('Contact', 'create', $update);
        }
    }

    /**
     * Trigger the event status update after a registration/update/cancel
     *
     * @param ChangingEvent $event
     *   token list event
     */
    public static function triggerUpdateContactEventStats($event)
    {
        if (!$event->hasErrors()) {
            $contact_id = (int) $event->getContactID();
            if ($contact_id) {
                self::updateContactEventStats($contact_id);
            }
        }
    }
}
