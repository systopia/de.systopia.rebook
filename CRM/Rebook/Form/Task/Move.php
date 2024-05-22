<?php
/*-------------------------------------------------------+
| SYSTOPIA Rebook Extension                              |
| Copyright (C) 2017-2019 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

require_once 'CRM/Core/Form.php';

use CRM_Rebook_ExtensionUtil as E;

/**
 * Form controller class
 */
class CRM_Rebook_Form_Task_Move extends CRM_Core_Form {

  protected $contribution_ids = array();

  function preProcess() {
    parent::preProcess();
    CRM_Utils_System::setTitle(E::ts('Move'));

    $admin = CRM_Core_Permission::check('administer CiviCRM');
    if (!$admin) {
      throw new Exception(E::ts('You do not have the permissions required to access this page.'));
    }

    if (empty($_REQUEST['contributionIds'])) {
      throw new Exception(E::ts("You need to specifiy a contribution to move."));
    }

    $this->contribution_ids = array((int) $_REQUEST['contributionIds']);

    // check if the contributions are all from the same contact
    CRM_Rebook_Form_Task_Rebook::checkSameContact($this->contribution_ids);
  }


  function buildQuickForm() {
    $contributionIds = implode(',', $this->contribution_ids);

    $this->add('text', 'contactId', E::ts('CiviCRM ID'), null, $required = true);
    $this->add('hidden', 'contributionIds', $contributionIds);
    $this->addDefaultButtons(E::ts('Move'));

    parent::buildQuickForm();
  }


  function addRules() {
    $this->addFormRule(array('CRM_Rebook_Form_Task_Move', 'moveRules'));
  }


  function postProcess() {
    $values = $this->exportValues();
    $contact_id = (int) trim($values['contactId']);
    self::move($this->contribution_ids, $contact_id);
    CRM_Core_Session::setStatus(E::ts("Moved %1 contribution(s) to contact [%2]", [1 => count($this->contribution_ids), 2 => $contact_id]), E::ts("Success"), 'info');
    parent::postProcess();
  }

  /**
   * Move all given contributions to the given target contact in the DB
   *
   * @param $contribution_ids  array   an array of contribution IDs
   * @param $contact_id        integer the target contact ID
   * @param $redirect_url      string  url to redirect to after the process
   */
  public static function move($contribution_ids, $contact_id, $redirect_url = NULL) {
    $contact_id = (int) $contact_id;
    $contribution_id_list = implode(',', $contribution_ids);
    if (empty($contribution_id_list) || empty($contact_id)) {
      return;
    }

    // save the old contact IDs
    $old_contributors = civicrm_api3('Contribution', 'get', [
        'id'           => ['IN' => $contribution_ids],
        'option.limit' => 0,
        'return'       => 'contact_id,id']);

    // move contributions
    CRM_Core_DAO::executeQuery("
            UPDATE civicrm_contribution
            SET contact_id = {$contact_id}
            WHERE id IN ({$contribution_id_list})");

    // move financial items
    CRM_Core_DAO::executeQuery("
            UPDATE civicrm_financial_item
            LEFT JOIN civicrm_line_item ON civicrm_line_item.id = civicrm_financial_item.entity_id AND civicrm_financial_item.entity_table = 'civicrm_line_item'
            SET contact_id = {$contact_id}
            WHERE civicrm_line_item.contribution_id IN ({$contribution_id_list})");

    // move contribution activities
    $contribution_activity_type = (int) civicrm_api3('OptionValue', 'getvalue', [
        'option_group_id' => 'activity_type',
        'name'            => 'Contribution',
        'return'          => 'value']);
    CRM_Core_DAO::executeQuery("
            UPDATE civicrm_activity_contact
            SET contact_id = {$contact_id}
            WHERE record_type_id = 3
              AND activity_id IN (SELECT civicrm_activity.id
                                  FROM civicrm_activity
                                  WHERE civicrm_activity.activity_type_id = {$contribution_activity_type}
                                  AND civicrm_activity.source_record_id IN ({$contribution_id_list}))");

    // add a note to each contribution
    $mover_id   = CRM_Core_Session::getLoggedInContactID();
    $mover_name = civicrm_api3('Contact','getvalue', ['id' => $mover_id, 'return' => 'display_name']);
    $mover = "{$mover_name} [{$mover_id}]";
    foreach ($old_contributors['values'] as $contribution) {
      civicrm_api3('Note', 'create', [
          'entity_id'    => $contribution['id'],
          'entity_table' => 'civicrm_contribution',
          'note'         => E::ts("Moved from contact [%1] on %2 by %3", [1 => $contribution['contact_id'], 2 => date('Y-m-d H:i:s'), 3 => $mover]),
          'subject'      => E::ts("Contribution moved!")
      ]);
    }

    // finally: redirect if requested (idk why this is in here)
    if ($redirect_url) {
      CRM_Utils_System::redirect($redirect_url);
    }
  }

  /**
   * Rule set for the rebooking forms
   */
  public static function moveRules($values) {
    $errors = array();
    $contactId = trim($values['contactId']);
    $contributionIds = $values['contributionIds'];

    if (!preg_match('/^\d+$/', $contactId)) { // check if is int
      $errors['contactId'] = E::ts('Please enter a CiviCRM ID!');
      return empty($errors) ? TRUE : $errors;
    }

    // validation for contact
    $contact = new CRM_Contact_BAO_Contact();
    $contact->id = (int) $contactId;

    if (!$contact->find(true)) {
      $errors['contactId'] = E::ts('A contact with CiviCRM ID %1 doesn\'t exist!', array(1 => $contactId, 'domain' => 'de.systopia.rebook'));
      return empty($errors) ? TRUE : $errors;
    }

    // don't move to households if not allowed
    $contactType = $contact->getContactType($contactId);
    if (!empty($contactType) && $contactType === 'Household' && TRUE !== \Civi::settings()->get('rebook_allow_households')) {
      $errors['contactId'] = E::ts('The target contact can not be a household!');
      return empty($errors) ? TRUE : $errors;
    }

    // target contact cannot be deleted
    $contactIsDeleted = $contact->is_deleted;
    if ($contactIsDeleted == 1) {
      $errors['contactId'] = E::ts('The target contact can not be in trash!');
      return empty($errors) ? TRUE : $errors;
    }

    // mustn't move recurring contributions
    $arr = explode(",", $contributionIds);
    foreach ($arr as $contributionId) {
      $contribution = new CRM_Contribute_DAO_Contribution();
      $contribution->id = $contributionId;
      if ($contribution->find(true)) {
        if (!empty($contribution->contribution_recur_id)) {
          $errors['contactId'] = E::ts("You mustn't move recurring contributions.", [1 => $contributionId]);
          return empty($errors) ? TRUE : $errors;
        }
      }
    }

    return empty($errors) ? TRUE : $errors;
  }
}
