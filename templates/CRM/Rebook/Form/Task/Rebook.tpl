{*-------------------------------------------------------+
| SYSTOPIA Rebook Extension                              |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+-------------------------------------------------------*}

<div class="crm-form-block crm-block crm-contact-task-pdf-form-block">
  {* HEADER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
  </div>

  <div class="messages status no-popup">
      <div class="icon inform-icon"></div>
      {if $totalSelectedContributions gt 1}
      <p>{ts domain="de.systopia.rebook"}Are you sure you want to rebook the selected contributions?{/ts}</p>
      <p>{ts domain="de.systopia.rebook"}Number of selected contributions:{/ts} {$totalSelectedContributions}</p><b/>
      {else}
      <p>{ts domain="de.systopia.rebook"}Are you sure you want to rebook the contribution?{/ts}</p>
      {/if}
  </div>

  <p><strong>{ts domain="de.systopia.rebook"}Please enter the target CiviCRM ID?{/ts}</strong></p>


  {$form.contactId.label}<br />
  {$form.contactId.html}
  {$form.contributionIds.html}
  <br />

  {* FOOTER *}
  <div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
