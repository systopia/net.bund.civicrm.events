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

require_once 'events.civix.php';
use CRM_Events_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function events_civicrm_config(&$config)
{
    _events_civix_civicrm_config($config);

    $dispatcher = new \Civi\RemoteDispatcher();

    // ADD REMOTE EVENT PROFILE
    $dispatcher->addUniqueListener(
        'civi.remoteevent.get.result',
        ['CRM_Events_RemoteEventModifications', 'overrideRegistrationRestrictions']
    );

    $dispatcher->addUniqueListener(
        'civi.remoteevent.registration.validate',
        ['CRM_Events_RemoteEventModifications', 'validateRegistrationRestrictions'],
        -500 // run late
    );

    $dispatcher->addUniqueListener(
        'civi.remoteevent.registration.submit',
        ['CRM_Events_Logic', 'triggerUpdateContactEventStats'], CRM_Remoteevent_Registration::AFTER_PARTICIPANT_CREATION);


    // ADD REMOTE CONTACT PROFILE
    $dispatcher->addUniqueListener(
        'civi.remotecontact.getprofiles',
        ['CRM_Events_Profile_EventDaysProfile', 'registerProfiles']);

}

/**
 * POST hook
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post/
 */
function events_civicrm_post($op, $objectName, $objectId, &$objectRef) {
    if ($objectName == 'Participant' && $objectId) {
        if ($op == 'create' || $op == 'edit' || $op == 'delete') {
            // the participant object was manipulated -> update event data via callback (after this transaction)
            if (CRM_Core_Transaction::isActive()) {
                CRM_Core_Transaction::addCallback(
                    CRM_Core_Transaction::PHASE_POST_COMMIT,
                    'events_civicrm_post_participant_update',
                    [$objectId, $objectRef]);
            } else {
                events_civicrm_post_participant_update($objectId, $objectRef);
            }
        }
    }
}

/**
 * Participant update callback: trigger contact updates
 */
function events_civicrm_post_participant_update($participant_id, $participantBAO) {
    try {
        // find the contact_id
        if (isset($participantBAO->contact_id)) {
            $contact_id = (int) $participantBAO->contact_id;
        } else {
            $contact_id = (int) civicrm_api3('Participant', 'getvalue', [
                'id'     => $participant_id,
                'return' => 'contact_id']);
        }

        // finally call the update
        CRM_Events_Logic::updateContactEventStats($contact_id);

    } catch (Exception $ex) {
        Civi::log()->debug(E::LONG_NAME . ': exception prevented contact update: ' . $ex->getMessage());
    }
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function events_civicrm_install()
{
    _events_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function events_civicrm_enable()
{
    _events_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function events_civicrm_navigationMenu(&$menu)
{
    _events_civix_insert_navigation_menu(
        $menu,
        'Administer/CiviEvent',
        [
            'label' => E::ts('BUND Event Configuration'),
            'name' => 'bund_event_configuration',
            'url' => 'civicrm/event/bund/settings',
            'permission' => 'administer CiviCRM',
            'operator' => 'OR',
            'separator' => 0,
        ]
    );
    _events_civix_navigationMenu($menu);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 *
 * function events_civicrm_preProcess($formName, &$form) {
 *
 * } // */
