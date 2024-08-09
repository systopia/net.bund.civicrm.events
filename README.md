# BUND Events (net.bund.civicrm.events)

## Requirements

* PHP v7.2+
* CiviCRM 5.28+

## Check event registrations for irregularities

If an event registration has one of the specified status values, the registration is checked for irregularities. If there are any irregularities, a notification email is sent from a configurable e-mail address to several civicrm contacts that can be configured as well.

It is checked for:
- overlapping periods with other event registrations
- any existing age restrictions

The message templates used can be found and changed at **Mailings > Message Templates > System Workflow Messages**.
