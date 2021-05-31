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
 * This profile will provide personal data of the logged in contact
 */
class CRM_Events_Profile_MyDataProfile extends CRM_Remotetools_RemoteContactProfile
{
    // ID/name of the profile
    const PROFILE_NAME = 'bund_events_my_data';

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
        return E::ts("Volunteer - My Data");
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
     * Get the list of (internal) fields to be returned.
     *  This can be overwritten by the profile
     *
     * @param $request RemoteContactGetRequest
     *   the request to execute
     *
     * @return array
     */
    public function getReturnFields($request)
    {
        // add required fields
        $required_fields = [
            'id' => 1,
            'display_name' => 1,
            'street_address' => 1,
            'supplemental_address_1' => 1,
            'supplemental_address_2' => 1,
            'postal_code' => 1,
            'city' => 1,
            'email' => 1,
            'phone' => 1,
            'freiwillige_zusatzinfos.freiwillige_regionalstelle' => 1,
            'freiwillige_zusatzinfos.freiwillige_regionalstellenbetreuerin' => 1,
        ];
        CRM_Events_CustomData::resolveCustomFields($required_fields);
        return array_keys($required_fields);
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

        // this is the OWN my data profile
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

        foreach (array_keys($reply_records) as $index) {
            // build record - this should only be one
            $old_record = $reply_records[$index];
            CRM_Events_CustomData::labelCustomFields($old_record);
            $new_record = [
                'display_name' => CRM_Utils_Array::value('display_name', $old_record),
                'street_address' => CRM_Utils_Array::value('street_address', $old_record),
                'supplemental_address_1' => CRM_Utils_Array::value('supplemental_address_1', $old_record),
                'supplemental_address_2' => CRM_Utils_Array::value('supplemental_address_2', $old_record),
                'postal_code' => CRM_Utils_Array::value('postal_code', $old_record),
                'city' => CRM_Utils_Array::value('city', $old_record),
                'email' => CRM_Utils_Array::value('email', $old_record),
                'phone' => CRM_Utils_Array::value('phone', $old_record),
                'freiwillige_regionalstelle' => CRM_Utils_Array::value('freiwillige_zusatzinfos.freiwillige_regionalstelle', $old_record),
                'supervisor_display_name' => '', // will be set below
                'supervisor_email' => '', // will be set below
                'supervisor_phone' => '', // will be set below
                'service_start_date' => '', // will be set below
                'service_end_date' => '', // will be set below
            ];

            // look up supervisor
            if (!empty($old_record['freiwillige_zusatzinfos.freiwillige_regionalstellenbetreuerin'])) {
                $supervisor = civicrm_api3('Contact', 'getsingle', [
                   'id' => $old_record['freiwillige_zusatzinfos.freiwillige_regionalstellenbetreuerin'],
                   'return' => 'display_name,phone,email'
                ]);
                $new_record['supervisor_display_name'] = CRM_Utils_Array::value('display_name', $supervisor);
                $new_record['supervisor_email'] = CRM_Utils_Array::value('email', $supervisor);
                $new_record['supervisor_phone'] = CRM_Utils_Array::value('phone', $supervisor);
            }

            // look up service
            try {
                $relationship_type_id = civicrm_api3('RelationshipType', 'getvalue',
                                                     ['name_a_b' => 'ist Freiwillige* bei', 'return' => 'id']);
                $active_service_relationships = civicrm_api3('Relationship', 'get', [
                    'relationship_type_id' => $relationship_type_id,
                    'return'       => 'start_date,end_date',
                    'option.limit' => 1,
                    'contact_id_a' => $old_record['id'],
                    'is_active'    => 1,
                    'option.sort'  => 'id desc'
                ]);
                if (!empty($active_service_relationships['values'])) {
                    // there should either be zero or one (api call was limited)
                    foreach ($active_service_relationships['values'] as $active_service_relationship) {
                        $new_record['service_start_date'] = CRM_Utils_Array::value('start_date', $active_service_relationship);
                        $new_record['service_end_date']   = CRM_Utils_Array::value('end_date', $active_service_relationship);
                    }
                }
            } catch (CiviCRM_API3_Exception $ex) {
                Civi::log()->debug("Error fetching relationship/type for contact [{$old_record['id']}].");
            }

            // replace the record
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
        // add core fields
        $fields_collection->setFieldSpec('display_name', [
            'name'          => 'display_name',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Display Name"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('street_address', [
            'name'          => 'street_address',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Street Address"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('supplemental_address_1', [
            'name'          => 'supplemental_address_1',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Supplemental Address 1"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('supplemental_address_2', [
            'name'          => 'supplemental_address_2',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Supplemental Address 2"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('postal_code', [
            'name'          => 'postal_code',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Postal Code"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('city', [
            'name'          => 'city',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("City"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('email', [
            'name'          => 'email',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Email"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('phone', [
            'name'          => 'phone',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Phone"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('freiwillige_regionalstelle', [
            'name'          => 'freiwillige_regionalstelle',
            'type'          => CRM_Utils_Type::T_ENUM,
            'title'         => E::ts("Regionalstelle"),
            'options'       => CRM_Remotetools_DataTools::getOptions('regionalstellen'),
            'localizable'   => 0,
            'serialize'     => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => false,
        ]);
        $fields_collection->setFieldSpec('supervisor_display_name', [
            'name'          => 'supervisor_display_name',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Supervisor - Display Name"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('supervisor_email', [
            'name'          => 'supervisor_email',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Supervisor - Email"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('supervisor_phone', [
            'name'          => 'supervisor_phone',
            'type'          => CRM_Utils_Type::T_STRING,
            'title'         => E::ts("Supervisor - Phone"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('service_start_date', [
            'name'          => 'service_start_date',
            'type'          => CRM_Utils_Type::T_DATE,
            'title'         => E::ts("Service - Start Date"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
        $fields_collection->setFieldSpec('service_end_date', [
            'name'          => 'service_end_date',
            'type'          => CRM_Utils_Type::T_DATE,
            'title'         => E::ts("Service - End Date"),
            'localizable'   => 0,
            'api.filter'    => 1,
            'api.sort'      => 1,
            'is_core_field' => true,
        ]);
    }
}
