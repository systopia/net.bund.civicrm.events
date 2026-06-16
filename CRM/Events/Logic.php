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
    const EVENT_DAYS_TOTAL   = 'teilnehmer_zusatzinfo.teilnehmer_gesamttage_anmeldung';

    const RESTRICT_ATTENDED = 'attended';
    const RESTRICT_BOOKED   = 'booked';
    const PARTICIPANT_STATUS_ATTENDED = 'Attended';
    const PARTICIPANT_STATUS_BOOKED   = 'Registered';

    // currently not used, relationship(s) defined via settings, using is_active flag at relationship
    //    const RELATIONSHIP_NAME         = 'ist Freiwillige* bei';
    //    const RELATIONSHIP_GROUP_NAME   = 'einsatz_zusatzinfos';
    //    const RELATIONSHIP_STATUS_FIELD = 'einsatz_status';
    //    const RELATIONSHIP_STATUS_OK    = [1,2];


    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Get a comma separated list of participant status IDs that are not to be considered
     *   for calculations
     *
     * @return string
     */
    public static function getExcludedParticipantStatusIdList(): string
    {
        // @todo add a config option?
        static $excluded_status_ids = null;
        if ($excluded_status_ids === null) {
            $excluded_status_ids = CRM_Core_DAO::singleValueQuery("
                    SELECT GROUP_CONCAT(id)
                    FROM civicrm_participant_status_type
                    WHERE name IN ('Cancelled','Rejected','Expired','Transferred')");
            if (!is_string($excluded_status_ids) || $excluded_status_ids === '') {
                Civi::log()->warning("BUND Events: cannot find any of the excluded status types ('Cancelled','Rejected','Expired','Transferred').");
                $excluded_status_ids = '-1'; // avoid SQL errors
            }
        }
        return $excluded_status_ids;
    }

    public static function getAttendedParticipantStatusIdList(): string
    {
        static $attended_status_ids = null;
        if ($attended_status_ids === null) {
            $attended_status_ids = CRM_Core_DAO::singleValueQuery(
                "SELECT GROUP_CONCAT(id) FROM civicrm_participant_status_type WHERE name = %1",
                [1 => [self::PARTICIPANT_STATUS_ATTENDED, 'String']]
            );
            if (!is_string($attended_status_ids) || $attended_status_ids === '') {
                Civi::log()->warning("BUND Events: cannot find the 'Attended' participant status type.");
                $attended_status_ids = '-1';
            }
        }
        return $attended_status_ids;
    }

    public static function getBookedParticipantStatusIdList(): string
    {
        static $booked_status_ids = null;
        if ($booked_status_ids === null) {
            $configured = Civi::settings()->get('bund_event_participant_status_types');
            $status_ids = is_array($configured) ? array_map('intval', $configured) : [];
            if ($status_ids === []) {
                $registered = CRM_Core_DAO::singleValueQuery(
                    "SELECT GROUP_CONCAT(id) FROM civicrm_participant_status_type WHERE name = %1",
                    [1 => [self::PARTICIPANT_STATUS_BOOKED, 'String']]
                );
                if (is_string($registered) && $registered !== '') {
                    $status_ids = array_map('intval', explode(',', $registered));
                }
            }
            $attended_ids = array_map('intval', explode(',', self::getAttendedParticipantStatusIdList()));
            $status_ids = array_values(array_diff($status_ids, $attended_ids));
            $booked_status_ids = $status_ids === [] ? '-1' : implode(',', $status_ids);
        }
        return $booked_status_ids;
    }

    /**
     * Check if the event registration restrictions should be
     *   applied to the given event
     *
     * @param array<string, mixed> $event_data
     *   event data, in particular containing event_type / id
     *
     * @return bool
     *   should the restriction be applied?
     */
    public static function shouldApplyRegistrationRestrictions(array $event_data): bool
    {
        $event_types = Civi::settings()->get('bund_event_types');
        if (!is_array($event_types) || $event_types === []) {
            return true;
        }

        // we look for certain event types
        if (!isset($event_data['event_type_id'])) {
            $event_data['event_type_id'] = civicrm_api3('Event', 'getvalue', [
                'id' => $event_data['id'],
                'return' => 'event_type_id'
            ]);
        }

        // return true if this is one of our event types
        return in_array(self::toInt($event_data['event_type_id']), array_map('intval', $event_types), true);
    }

    /**
     * Check if the total contingent has not been exceeded
     *
     * @param integer $contact_id
     *   contact that wants to register
     *
     * @param array<string, mixed> $event
     *   event data
     *
     * @return boolean
     *   is there still some contingent left?
     */
    public static function contactStillHasContingentLeftForEvent(int $contact_id, array $event): bool
    {
        try {
            $event_contingent_left = self::getContactEventContingentLeft($contact_id);
            $event_days = self::getPersonalEventDays($event, $contact_id);
            Civi::log()->debug("Contact [{$contact_id}] needs {$event_days} day(s) and has {$event_contingent_left} day(s) left");
            return $event_contingent_left >= $event_days;
        } catch (\CRM_Core_Exception $ex) {
            Civi::log()->debug("Error in contactStillHasContingentLeftForEvent, contact ID {$contact_id}: " . $ex->getMessage());
            return false;
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
     * @return boolean
     *   number of days granted to the contact
     */
    public static function contactHasRelationship(int $contact_id, int $event_id): bool
    {
        $required_relationships = Civi::settings()->get('bund_event_relationship_types');
        if (!is_array($required_relationships) || $required_relationships === []) {
            // no relationship set -> great!
            return true;
        }

        // build SQL query
        $relationships = $relationship_joins = [];
        foreach ($required_relationships as $relationship_spec) {
            if (preg_match('/^([0-9]+)([ab])$/', (string) $relationship_spec, $matches) === 1) {
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
                 throw new \CRM_Core_Exception("Invalid relationship spec in 'bund_event_contingent_field': " . $relationship_spec);
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
    public static function getContactEventContingentLeft(int $contact_id): int
    {
        $contingent_data = CRM_Events_Logic::getContactEventContingentData($contact_id);
        // don't do this: $contingent_used = $contingent_data[self::EVENT_DAYS_BOOKED] + $contingent_data[self::EVENT_DAYS_USED];
        // calculate LIVE instead
        $contingent_used = self::getContactEventContingentUsed($contact_id);

        return self::toInt($contingent_data[self::EVENT_DAYS_GRANTED]) - $contingent_used;
    }

    /**
     * Get the number of days an event counts as for this particular contact
     *
     * @param array<string, mixed> $event
     *   event data
     *
     * @param integer $contact_id
     *   contact ID, since a contact's participant can overwrite the event days
     *   see https://pws.bund.net/issues/4691 item 3
     *
     * @return integer
     *   number of days
     */
    public static function getPersonalEventDays(array $event, int $contact_id): int
    {
        $event_id = self::toInt($event['id'] ?? 0);
        if ($event_id === 0) {
            return 0;
        }

        // if the EVENT_DAYS_TOTAL field is set of one of the participants,
        //   then that overrules the value given by the event
        $custom_table = CRM_Events_CustomData::getGroupTable('teilnehmer_zusatzinfo');
        $days_override_field = CRM_Events_CustomData::getCustomField('teilnehmer_zusatzinfo', 'teilnehmer_gesamttage_anmeldung');
        $days_skipped_field  = CRM_Events_CustomData::getCustomField('teilnehmer_zusatzinfo', 'teilnehmer_fehltage_unentschuldigt');
        $excluded_status_ids = self::getExcludedParticipantStatusIdList();
        /** @var \CRM_Core_DAO $event_days_override */
        $event_days_override = CRM_Core_DAO::executeQuery("
            SELECT 
                   MAX({$days_override_field['column_name']}) AS event_days_override,
                   MAX({$days_skipped_field['column_name']})  AS event_days_skipped
            FROM civicrm_participant participant
            LEFT JOIN {$custom_table} additional_participant_values
                   ON additional_participant_values.entity_id = participant.id
            WHERE participant.contact_id = {$contact_id}
              AND participant.event_id = {$event_id}
              AND participant.status_id NOT IN ({$excluded_status_ids})
            GROUP BY participant.contact_id;");
        $event_days_override->fetch();

        // first get the total event_days
        $override = intval($event_days_override->event_days_override ?? 0);
        if ($override !== 0) {
            $event_days = $override;
        } else {
            $event_days = self::getEventDays($event);
        }

        // potentially subtract the skipped days - those will not be counted
        $skipped = intval($event_days_override->event_days_skipped ?? 0);
        if ($skipped !== 0) {
            $event_days = $event_days - $skipped;
        }

        return $event_days;
    }

    /**
     * Get the number of days an event counts for
     *
     * @param array<string, mixed> $event
     *   event data
     *
     * @return integer
     *   number of days
     */
    public static function getEventDays(array $event): int
    {
        $event_id = self::toInt($event['id'] ?? 0);
        if ($event_id === 0) {
            return 0;
        }

        // cache results
        /** @var array<int, int> $event_days */
        static $event_days = [];
        if (isset($event_days[$event_id])) {
            return $event_days[$event_id];
        }

        // load event, if necessary
        $days_override = CRM_Events_CustomData::getCustomFieldKey('seminar_zusatzinfo', 'seminar_gesamtzahl_tage');
        if (($event['start_date'] ?? '') === '') {
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
        $custom_days = self::toInt($event['seminar_zusatzinfo.seminar_gesamtzahl_tage'] ?? 0);
        if ($event_day_count === null && $custom_days !== 0) {
            $event_day_count = $custom_days;
        }

        // if end_date is empty, it's a one-day affair
        if ($event_day_count === null) {
            // make sure the end date is loaded
            if (!isset($event['end_date'])) {
                $event['end_date'] = civicrm_api3('Event', 'getvalue', ['id' => $event_id, 'return' => 'event_end_date']);
            }
            if (($event['end_date'] ?? '') === '') {
                $event_day_count = 1;
            }
        }

        // else calculate the 'temporal distance' in days and add one
        if ($event_day_count === null) {
            $start_ts = strtotime(strval($event['start_date'] ?? ''));
            $end_ts   = strtotime(strval($event['end_date'] ?? ''));
            if ($start_ts === false || $end_ts === false) {
                $event_day_count = 1;
            } else {
                $start_date = date('Y-m-d', $start_ts);
                $end_date   = date('Y-m-d', $end_ts);
                $seconds_difference = (int) strtotime($end_date) - (int) strtotime($start_date);
                $days_difference = (int) ($seconds_difference / (60 * 60 * 24));
                $event_day_count = 1 + $days_difference;
            }
        }

        // cache and return
        $event_days[$event_id] = $event_day_count;
        return $event_day_count;
    }


    /**
     * Get the contingent data from the contact
     *
     * @param integer $contact_id
     *   contact ID
     *
     * @return array<string, mixed>
     *   contingent data. fields:
     *    contact_id
     *    EVENT_DAYS_GRANTED,
     *    EVENT_DAYS_BOOKED,
     *    EVENT_DAYS_USED,
     *    EVENT_DAYS_LEFT
     */
    protected static function getContactEventContingentData(int $contact_id, bool $cached = true): array {
        // caching
        static $contact_event_contingent = [];
        if (!$cached) {
            unset($contact_event_contingent[$contact_id]);
        }
        if (isset($contact_event_contingent[$contact_id])) {
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
        $contingent_data[self::EVENT_DAYS_GRANTED]  = intval($contingent_data[self::EVENT_DAYS_GRANTED] ?? 0);
        $contingent_data[self::EVENT_DAYS_USED]     = intval($contingent_data[self::EVENT_DAYS_USED] ?? 0);
        $contingent_data[self::EVENT_DAYS_BOOKED]   = intval($contingent_data[self::EVENT_DAYS_BOOKED] ?? 0);
        $contingent_data[self::EVENT_DAYS_LEFT]     = intval($contingent_data[self::EVENT_DAYS_LEFT] ?? 0);
        $contingent_data[self::TOTAL_DAYS_MISSED]   = intval($contingent_data[self::TOTAL_DAYS_MISSED] ?? 0);
        $contingent_data[self::TOTAL_DAYS_SKIPPED]  = intval($contingent_data[self::TOTAL_DAYS_SKIPPED] ?? 0);

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
     * @param string|null $restrict
     *   can have the following values:
     *    self::RESTRICT_ATTENDED: only counts attended participations (days completed)
     *    self::RESTRICT_BOOKED: only counts booked (registered, not yet attended) participations
     *    otherwise: all (eligible) participations
     *
     * @return integer
     *   number of days used by the contact
     *
     * @todo consider roles? consider multiple participants per contact&event?
     */
    public static function getContactEventContingentUsed(int $contact_id, ?string $restrict = null): int
    {
        $number_of_days = 0;

        if ($contact_id !== 0) {
            // check if we restrict to certain event types
            $HAS_THE_RIGHT_EVENT_TYPE = 'TRUE';
            $event_types = Civi::settings()->get('bund_event_types');
            if (is_array($event_types) && $event_types !== []) {
                $event_type_list = implode(',', array_map('intval', $event_types));
                $HAS_THE_RIGHT_EVENT_TYPE = "event.event_type_id IN ({$event_type_list})";
            }

            switch ($restrict) {
                case self::RESTRICT_ATTENDED:
                    $status_id_list = self::getAttendedParticipantStatusIdList();
                    break;

                case self::RESTRICT_BOOKED:
                    $status_id_list = self::getBookedParticipantStatusIdList();
                    break;

                default:
                    $status_id_list = self::getAttendedParticipantStatusIdList()
                        . ',' . self::getBookedParticipantStatusIdList();
                    break;
            }

            // build query
            $query = "
                SELECT GROUP_CONCAT(DISTINCT(event.id)) AS events
                FROM civicrm_participant participant
                LEFT JOIN civicrm_event  event
                       ON event.id = participant.event_id
                WHERE participant.contact_id = {$contact_id}
                  AND participant.status_id IN ({$status_id_list})
                  AND {$HAS_THE_RIGHT_EVENT_TYPE}";
            $events = CRM_Core_DAO::singleValueQuery($query);
            foreach (explode(',', $events ?? '') as $event_id) {
                if ($event_id !== '') {
                    $number_of_days += self::getPersonalEventDays(['id' => $event_id], $contact_id);
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
     * @return array{0: int, 1: int}
     *   [missed_days, skipped_days]
     *
     * @see https://pws.bund.net/issues/4691
     */
    public static function getMissedDays(int $contact_id): array
    {
        if ($contact_id !== 0) {
            // check if we restrict to certain event types
            $HAS_THE_RIGHT_EVENT_TYPE = 'TRUE';
            $event_types = Civi::settings()->get('bund_event_types');
            if (is_array($event_types) && $event_types !== []) {
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
            /** @var \CRM_Core_DAO $data */
            $data = CRM_Core_DAO::executeQuery($query);
            $data->fetch();
            return [
                intval($data->days_missed ?? 0),
                intval($data->days_skipped ?? 0),
            ];
        }
        return [0, 0];
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
    public static function updateContactEventStats(int $contact_id): void
    {
        if ($contact_id === 0) {
            return;
        }

        $current_values = self::getContactEventContingentData($contact_id, false);
        $update = [];
        // check days used
        $days_used = self::getContactEventContingentUsed($contact_id, self::RESTRICT_ATTENDED);
        if ($current_values[self::EVENT_DAYS_USED] !== $days_used) {
            $update[self::EVENT_DAYS_USED] = $days_used;
        }

        // check days booked
        $days_booked = self::getContactEventContingentUsed($contact_id, self::RESTRICT_BOOKED);
        if ($current_values[self::EVENT_DAYS_BOOKED] !== $days_booked) {
            $update[self::EVENT_DAYS_BOOKED] = $days_booked;
        }

        $days_left = self::toInt($current_values[self::EVENT_DAYS_GRANTED]) - $days_used - $days_booked;
        if ($current_values[self::EVENT_DAYS_LEFT] !== $days_left) {
            $update[self::EVENT_DAYS_LEFT] = $days_left;
        }

        // check missed/skipped days (see BUND-4691)
        [$missed_days, $skipped_days] = self::getMissedDays($contact_id);
        if ($current_values[self::TOTAL_DAYS_MISSED] !== $missed_days) {
            $update[self::TOTAL_DAYS_MISSED] = $missed_days;
        }
        if ($current_values[self::TOTAL_DAYS_SKIPPED] !== $skipped_days) {
            $update[self::TOTAL_DAYS_SKIPPED] = $skipped_days;
        }

        // update if there is differences
        if ($update !== []) {
            $update['id'] = $contact_id;
            CRM_Events_CustomData::resolveCustomFields($update);
            civicrm_api3('Contact', 'create', $update);
        }
    }

    /**
     * Trigger the event status update after a registration/update/cancel
     *
     * @param ChangingEvent $event
     *   token list event
     */
    public static function triggerUpdateContactEventStats(ChangingEvent $event): void
    {
        if (!$event->hasErrors()) {
            $contact_id = (int) $event->getContactID();
            if ($contact_id !== 0) {
                self::updateContactEventStats($contact_id);
            }
        }
    }
}
