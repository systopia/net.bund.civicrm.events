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

require_once 'events.civix.php';
use CRM_Events_ExtensionUtil as E;

/**
 * Contact.update_event_stats specification
 *
 * @param array $spec
 *   API specification blob
 */
function _civicrm_api3_contact_update_event_stats_spec(&$spec)
{
    // add extra fields
    $spec['id'] = [
        'name'         => 'id',
        'api.required' => 0,
        'title'        => E::ts('Contact ID'),
        'description'  => E::ts('Contact ID of the contact to update'),
    ];
    $spec['ids'] = [
        'name'         => 'ids',
        'api.required' => 0,
        'title'        => E::ts('List/array of contact IDs'),
        'description'  => E::ts('This can be a comma-separated list or an array'),
    ];
}

/**
 * Contact.update_event_stats implementation
 *
 * Will update the contact's event stats
 *
 * @param array $params
 *   API call parameters
 *
 * @return array
 *   API3 response
 */
function civicrm_api3_contact_update_event_stats($params)
{
    if (empty($params['ids'])) {
        $contact_ids = [];
    } else {
        if (is_array($params['ids'])) {
            $contact_ids = $params['ids'];
        } else {
            $contact_ids = explode(',', $params['ids']);
        }
    }

    // make sure they're all INTs
    $contact_ids = array_map('intval', $contact_ids);

    // add the single contact ID
    if (!empty($params['id'])) {
        $contact_ids[] = (int) $params['id'];
    }

    // remove duplicates
    $contact_ids = array_unique($contact_ids);

    // trigger all calculations
    foreach ($contact_ids as $contact_id) {
        CRM_Events_Logic::updateContactEventStats($contact_id);
    }

    // done
    return civicrm_api3_create_success();
}
