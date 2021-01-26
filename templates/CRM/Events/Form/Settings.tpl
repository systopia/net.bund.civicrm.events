{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

<div class="crm-content">
  <div class="crm-section">
    <div class="label">{$form.bund_event_types.label}</div>
    <div class="content">{$form.bund_event_types.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.bund_event_relationship_types.label}</div>
    <div class="content">{$form.bund_event_relationship_types.html}</div>
    <div class="clear"></div>
  </div>
{*  <div class="crm-section">*}
{*    <div class="label">{$form.bund_event_relationship_offset.label}</div>*}
{*    <div class="content">{$form.bund_event_relationship_offset.html}</div>*}
{*    <div class="clear"></div>*}
{*  </div>*}
{*  <div class="crm-section">*}
{*    <div class="label">{$form.bund_event_contingent_field.label}</div>*}
{*    <div class="content">{$form.bund_event_contingent_field.html}</div>*}
{*    <div class="clear"></div>*}
{*  </div>*}
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
