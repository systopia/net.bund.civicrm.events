<?php
/*-------------------------------------------------------+
| BUND Event Customisations                              |
| Copyright (C) 2021 SYSTOPIA                            |
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
use Civi\RemoteContact\GetFieldsEvent;
use Civi\RemoteContact\RemoteContactGetRequest;
use Civi\RemoteContact\GetRemoteContactProfiles;

/**
 * This profile will provide event days data
 */
class CRM_Events_Profile_EventDaysProfile extends CRM_Remotetools_RemoteContactProfile
{
    // ID/name of the profile
    const PROFILE_NAME = 'bund_events_seminar_days';

    // fields to present
    const PROFILE_FIELDS = [
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_pflicht',
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_gebucht',
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_geleistet',
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_offen',
//        'seminar_zusatzinfo.politische_bildung_tage_gebucht',
//        'seminar_zusatzinfo.politische_bildung_tage_geleistet',
//        'seminar_zusatzinfo.politische_bildung_tage_offen',
    ];

    /**
     * Get the profile's ID
     *
     * @return string
     *   profile ID
     */
    public function getProfileID()
    {
        return self::PROFILE_NAME;
    }

    /**
     * Get the profile's (human readable) name
     *
     * @return string
     *   profile ID
     */
    public function getProfileName()
    {
        return E::ts("Own Seminar Days");
    }

    /**
     * Is this profile suitable for the RemoteContact.get_self method?
     *
     * @param $request RemoteContactGetRequest
     *   the request to execute
     *
     * @return boolean
     *   does this profile only return the data of the caller?
     */
    public function isOwnDataProfile($request)
    {
        return true;
    }

    /**
     * Initialise the profile. This is a good place to do some sanity checks
     *
     * @param $request RemoteContactGetRequest
     *   the request to execute
     *
     */
    public function initProfile($request)
    {
        // implement this to format the results before delivery
        $contact_id = $request->getCallerContactID();
        if (!$contact_id) {
            $request->addError(E::ts("This profile can only be used when the caller is identified."));
        }
    }

    /**
     * Get a mapping of external field names to
     *  the internal ones,
     *   e.g. ['my_super_field' => 'custom_23']
     *
     * @return array
     *   [external field name => internal field name]
     */
    public function getExternalToInternalFieldMapping()
    {
        $field_mapping = $this->getRequestedFieldMapping();
        $external_mapping = [];
        foreach ($field_mapping as $internal_name => $full_name) {
            $external_name = explode('.', $full_name)[1];
            $external_mapping[$external_name] = $internal_name;
        }
        return $external_mapping;
    }

    /**
     * If the profile wants to restrict any fields
     *  This is meant to be overwritten by the profile
     *
     * @param $request RemoteContactGetRequest
     *   the request to execute

     * @param array $request_data
     *    the request parameters, to be edited in place
     *
     */
    public function applyRestrictions($request, &$request_data)
    {
        parent::applyRestrictions($request, $request_data);
        $request_data['contact_type'] = 'Individual';
        $request_data['contact_sub_type'] = 'Freiwillige';

        // this is the OWN event days profile
        $request_data['id'] = $request->getCallerContactID();
    }

    /**
     * This is a point where the profile can re-format the results
     *
     * @param $request RemoteContactGetRequest
     *   the request to execute
     *
     * @param array $reply_records
     *    the current reply records to edit in-place
     */
    public function filterResult($request, &$reply_records)
    {
        parent::filterResult($request, $reply_records);

        $fields = $this->getInternalToExternalFieldMapping();
        foreach (array_keys($reply_records) as $index) {
            $old_record = $reply_records[$index];
            $new_record = [];
            foreach ($fields as $civicrm_field => $field_name) {
                $new_record[$field_name] = CRM_Utils_Array::value($civicrm_field, $old_record, 0);
            }
            $reply_records[$index] = $new_record;
        }
    }

    /**
     * Add the profile's fields to the fields collection
     *
     * @param $fields_collection GetFieldsEvent
     */
    public function addFields($fields_collection)
    {
        $fields = $this->getRequestedFieldMapping();
        foreach ($fields as $civicrm_name => $field_name) {
            list($field_group, $field_name) = explode('.', $field_name);
            $custom_field = CRM_Events_CustomData::getCustomField($field_group, $field_name);
            $fields_collection->setFieldSpec($field_name, [
                     'name' => $field_name,
                     'type' => CRM_Utils_Type::T_INT,
                     'title' => $custom_field['label'],
                     'localizable' => 0,
                     'api.filter' => 0,
                     'api.sort' => 0,
                     'is_core_field' => false,
                 ]);
        }
    }


    /**
     * Return a mapping
     *  of custom_xx to fully qualified custom field name
     *  of all fields shown by this field
     */
    protected function getRequestedFieldMapping()
    {
        static $field_mapping = null;
        if ($field_mapping === null) {
            $field_mapping = [];
            foreach (self::PROFILE_FIELDS as $full_field_name) {
                $field_mapping[$full_field_name] = $full_field_name;
            }
            CRM_Events_CustomData::resolveCustomFields($field_mapping);
        }
        return $field_mapping;
    }



    /**
     * Register the profiles provided by this module itself.
     *
     * @param GetRemoteContactProfiles $profiles
     */
    public static function registerProfiles($profiles)
    {
        if ($profiles->matchesName(CRM_Events_Profile_EventDaysProfile::PROFILE_NAME)) {
            $profiles->addInstance(new CRM_Events_Profile_EventDaysProfile());
        }
        if ($profiles->matchesName(CRM_Events_Profile_MyDataProfile::PROFILE_NAME)) {
            $profiles->addInstance(new CRM_Events_Profile_MyDataProfile());
        }
    }
}
