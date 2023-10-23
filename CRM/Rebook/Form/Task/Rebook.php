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
class CRM_Rebook_Form_Task_Rebook extends CRM_Core_Form {

  protected $contribution_ids = array();


  function preProcess() {
    parent::preProcess();
    CRM_Utils_System::setTitle(E::ts('Rebook'));

    $admin = CRM_Core_Permission::check('edit contributions');
    if (!$admin) {
      throw new Exception(E::ts('You do not have the permissions required to access this page.'));
    }

    if (empty($_REQUEST['contributionIds'])) {
      throw new Exception(E::ts("You need to specifiy a contribution to rebook."));
    }

    $this->contribution_ids = array((int) $_REQUEST['contributionIds']);

    // check if the contributions are all from the same contact
    CRM_Rebook_Form_Task_Rebook::checkSameContact($this->contribution_ids);
  }


  function buildQuickForm() {
    $contributionIds = implode(',', $this->contribution_ids);

    $this->add('text', 'contactId', E::ts('CiviCRM ID'), null, $required = true);
    $this->add('hidden', 'contributionIds', $contributionIds);
    $this->addDefaultButtons(E::ts('Rebook'));

    parent::buildQuickForm();
  }


  function addRules() {
    $this->addFormRule(array('CRM_Rebook_Form_Task_Rebook', 'rebookRules'));
  }


  function postProcess() {
    $values = $this->exportValues();
    $contact_id = (int) trim($values['contactId']);
    self::rebook($this->contribution_ids, $contact_id);
    CRM_Core_Session::setStatus(E::ts("Re-booked %1 contribution(s) to contact [%2]", [1 => count($this->contribution_ids), 2 => $contact_id]), E::ts("Success"), 'info');
    parent::postProcess();
  }




  /**
   * Checks if the given contributions are of the same contact - one of the requirements for rebooking
   *
   * @param $contribution_ids  an array of contribution IDs
   *
   * @return the one contact ID or NULL
   */
  static function checkSameContact($contribution_ids, $redirect_url = NULL) {
    $contact_ids = array();

    foreach ($contribution_ids as $contributionId) {
      $params = array(
          'version' => 3,
          'sequential' => 1,
          'id' => $contributionId,
      );
      $contribution = civicrm_api('Contribution', 'getsingle', $params);

      if (empty($contribution['is_error'])) { // contribution exists
        array_push($contact_ids, $contribution['contact_id']);
      } else {
        CRM_Core_Session::setStatus(E::ts("At least one of the given contributions doesn't exist!"), E::ts("Error"), "error");
        CRM_Utils_System::redirect($redirect_url);
        return;
      }
    }

    $contact_ids = array_unique($contact_ids);
    if (count($contact_ids) > 1) {
      CRM_Core_Session::setStatus(E::ts('Moving/rebooking multiple contributions from different contacts is not allowed!'), "error");
      CRM_Utils_System::redirect($redirect_url);
      return NULL;
    } else {
      return reset($contact_ids);
    }
  }


  /**
   * Will rebook all given contributions to the given target contact
   *
   * @param $contribution_ids  array   an array of contribution IDs
   * @param $contact_id        integer the target contact ID
   * @param $redirect_url      string  url to redirect to after the process
   */
  public static function rebook($contribution_ids, $contact_id, $redirect_url = NULL) {
    $contact_id = (int) $contact_id;
    $excludeList = array('id', 'contribution_id', 'trxn_id', 'invoice_id', 'cancel_date', 'cancel_reason', 'address_id', 'contribution_contact_id', 'contribution_status_id');
    $cancelledStatus = CRM_Rebook_Legacycode_OptionGroup::getValue('contribution_status', 'Cancelled', 'name');
    $completedStatus = CRM_Rebook_Legacycode_OptionGroup::getValue('contribution_status', 'Completed', 'name');
    $contribution_fieldKeys = CRM_Contribute_DAO_Contribution::fieldKeys();
    $sepa_ooff_payment_id = CRM_Rebook_Legacycode_OptionGroup::getValue('payment_instrument', 'OOFF', 'name');

    // save recurring contribution status
    $recur_status = self::getRecurringContributionStatus($contribution_ids);

    $contribution_count = count($contribution_ids);
    $session = CRM_Core_Session::singleton();
    $rebooked = 0;

    foreach ($contribution_ids as $contributionId) {
      $params = array(
          'version' => 3,
          'sequential' => 1,
          'id' => $contributionId,
      );
      $contribution = civicrm_api('Contribution', 'getsingle', $params);

      if (empty($contribution['is_error'])) { // contribution exists
        // cancel contribution
        $params = array(
            'version'                 => 3,
            'contribution_status_id'  => $cancelledStatus,
            'cancel_reason'           => E::ts('Rebooked to CiviCRM ID %1', array(1 => $contact_id, 'domain' => 'de.systopia.rebook')),
            'cancel_date'             => date('YmdHis'),
            'currency'                => $contribution['currency'],    // see ticket #1455
            'id'                      => $contribution['id'],
        );
        $cancelledContribution = civicrm_api('Contribution', 'create', $params);
        if (!empty($cancelledContribution['is_error']) && !empty($cancelledContribution['error_message'])) {
          CRM_Core_Session::setStatus($cancelledContribution['error_message'], E::ts("Error"), "error");
        }

        // Now compile $attributes, taking the exclusionList into account
        $attributes = array(
            'version'                 => 3,
            'contribution_contact_id' => $contact_id,
            'contribution_status_id'  => $completedStatus,
            'payment_instrument_id'   => CRM_Rebook_Legacycode_OptionGroup::getValue('payment_instrument', $contribution['instrument_id'], 'id'), // this seems to be an API bug
        );
        foreach ($contribution as $key => $value) {

          if (!in_array($key, $excludeList) && in_array($key, $contribution_fieldKeys)) { // to be sure that this keys really exists
            $attributes[$key] = $value;
          }

          if (strstr($key, 'custom')) { // get custom fields
            // load custom field spec for exception handling
            $custom_field_id = substr($key, 7);
            $custom_field = civicrm_api('CustomField', 'getsingle', array('id'=>$custom_field_id,'version'=>3));

            // Exception 1: dates are not properly formatted
            if ($custom_field['data_type'] == 'Date') {
              if (!empty($value)) {
                $value = date('YmdHis', strtotime($value));
              }
            }
            $attributes[$key] = $value;
          }
        }

        // create new contribution
        $newContribution = civicrm_api('Contribution', 'create', $attributes);
        if (!empty($newContribution['is_error']) && !empty($newContribution['error_message'])) {
          CRM_Core_Session::setStatus($newContribution['error_message'], E::ts("Error"), "error");
        }

        // Exception handling for SEPA OOFF payments (org.project60.sepa extension)
        if (!empty($sepa_ooff_payment_id) && $attributes['payment_instrument_id'] == $sepa_ooff_payment_id) {
          CRM_Rebook_Form_Task_Rebook::fixOOFFMandate($contribution, $newContribution['id']);
        }

        // create rebook note
        $params = array(
            'version' => 3,
            'sequential' => 1,
            'note' => E::ts('Rebooked from CiviCRM ID %1', array(1 => $contribution['contact_id'], 'domain' => 'de.systopia.rebook')),
            'entity_table' => 'civicrm_contribution',
            'entity_id' => $newContribution['id']
        );
        $result = civicrm_api('Note', 'create', $params);


        // move all notes from the old contribution
        $notes = civicrm_api('Note', 'get', array('entity_id' => $contributionId, 'entity_table' => 'civicrm_contribution', 'version' => 3));
        if (!empty($notes['is_error'])) {
          Civi::log()->debug("de.systopia.rebook: Error while reading notes: ".$notes['error_message']);
        } else {
          foreach ($notes['values'] as $note) {
            $dao = new CRM_Core_DAO_Note();
            $dao->id = $note['id'];
            $dao->entity_id = $newContribution['id'];
            $dao->save();
          }
        }

        $rebooked += 1;
      }
    }

    // make sure the status of the recurring contributions haven't changed
    self::restoreRecurringContributionStatus($recur_status);

    if ($rebooked == $contribution_count) {
      CRM_Core_Session::setStatus(E::ts('%1 contribution(s) successfully rebooked!', array(1 => $contribution_count, 'domain' => 'de.systopia.rebook')), E::ts('Successfully rebooked!'), 'success');
    } else {
      Civi::log()->debug("de.systopia.rebook: Only $rebooked of $contribution_count contributions rebooked.");
      CRM_Core_Session::setStatus(E::ts('Please check your data and try again', array(1 => $contribution_count)), E::ts('Nothing rebooked!'), 'warning');
      CRM_Utils_System::redirect($redirect_url);
    }
  }


  /**
   * Rule set for the rebooking forms
   */
  static function rebookRules($values) {
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

    // mustn't rebook to households
    $contactType = $contact->getContactType($contactId);
    if (!empty($contactType) && $contactType == 'Household') {
      $errors['contactId'] = E::ts('The target contact can not be a household!');
      return empty($errors) ? TRUE : $errors;
    }

    // mustn't rebook to deleted contacts
    $contactIsDeleted = $contact->is_deleted;
    if ($contactIsDeleted == 1) {
      $errors['contactId'] = E::ts('The target contact can not be in trash!');
      return empty($errors) ? TRUE : $errors;
    }

    // only completed contributions can be rebooked
    $completed = CRM_Rebook_Legacycode_OptionGroup::getValue('contribution_status', 'Completed', 'name');
    $arr = explode(",", $contributionIds);
    foreach ($arr as $contributionId) {
      $contribution = new CRM_Contribute_DAO_Contribution();
      $contribution->id = $contributionId;
      if ($contribution->find(true)) {
        // only 'completed' contributions can be rebooked
        if ($contribution->contribution_status_id != $completed) {
          $errors['contactId'] = E::ts('The contribution with ID %1 is not completed!', array(1 => $contributionId, 'domain' => 'de.systopia.rebook'));
          return empty($errors) ? TRUE : $errors;
        }
      }
    }
    return empty($errors) ? TRUE : $errors;
  }

  /**
   * Fixes the problem, that the cloned contribution does not have a mandate.
   *
   * Approach is:
   *  1) move old (valid) mandate to new contribution
   *  2) create new (invalid) mandate and attach to old contribution
   *
   * @see org.project60.sepa extension
   */
  static function fixOOFFMandate($old_contribution, $new_contribution_id) {
    $old_mandate = civicrm_api('SepaMandate', 'getsingle', array('entity_id'=>$old_contribution['id'], 'entity_table'=>'civicrm_contribution', 'version' => 3));
    if (!empty($old_mandate['is_error'])) {
      CRM_Core_Session::setStatus($old_mandate['error_message'], E::ts("Error"), "error");
      return;
    }

    // find a new, unused, derived mandate reference to mark the old one
    $new_reference_pattern = $old_mandate['reference'].'REB%02d';
    $new_reference = '';
    for ($i = 1; $i <= 100; $i++) {
      $new_reference = sprintf($new_reference_pattern, $i);
      if (strlen($new_reference) > 35) {
        CRM_Core_Session::setStatus(E::ts("Cannot find a new mandate reference, exceeds 35 characters."), E::ts("Error"), "error");
        return;
      }

      // see if this reference already exists
      $exists = civicrm_api('SepaMandate', 'getsingle', array('reference' => $new_reference, 'version' => 3));
      if (empty($exists['is_error'])) {
        // found -> it exists -> damn -> keep looking...
        if ($i == 100) {
          // that's it, we tried... maybe something else is wrong
          CRM_Core_Session::setStatus(E::ts("Cannot find a new mandate reference"), E::ts("Error"), "error");
          break;
        } else {
          // keep looking!
          continue;
        }
      } else {
        // we found a reference
        break;
      }
    }

    // create an invalid clone of the mandate
    $new_mandate_data = array(
      'version'               => 3,
      'entity_id'             => $old_contribution['id'],
      'entity_table'          => 'civicrm_contribution',
      'status'                => 'INVALID',
      'reference'             => $new_reference,
      'source'                => $old_mandate['source'],
      'date'                  => date('YmdHis', strtotime($old_mandate['date'])),
      'validation_date'       => date('YmdHis', strtotime($old_mandate['validation_date'])),
      'creation_date'         => date('YmdHis', strtotime($old_mandate['creation_date'])),
      'first_contribution_id' => empty($old_mandate['first_contribution_id'])?'':$old_mandate['first_contribution_id'],
      'type'                  => $old_mandate['type'],
      'contact_id'            => $old_mandate['contact_id'],
      'iban'                  => $old_mandate['iban'],
      'bic'                   => $old_mandate['bic']);
    $create_clone = civicrm_api('SepaMandate', 'create', $new_mandate_data);
    if (!empty($create_clone['is_error'])) {
      CRM_Core_Session::setStatus($create_clone['error_message'], E::ts("Error"), "error");
      return;
    }

    // set old (original) mandate to new contribution
    $result = civicrm_api('SepaMandate', 'create', array('id' => $old_mandate['id'], 'entity_id' => $new_contribution_id, 'version' => 3));
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus($result['error_message'], E::ts("Error"), "error");
      return;
    }

    // modify new mandate's (invalid clone's) reference, in case it got overridden
    $result = civicrm_api('SepaMandate', 'create', array('id' => $create_clone['id'], 'reference' => $new_reference, 'version' => 3));
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus($result['error_message'], E::ts("Error"), "error");
      return;
    }
  }


  /**
   * Collect the status of all recurring contribution objects connected to those contributions
   *
   * @param $contribution_ids array contribution IDs
   * @return array map contribution_rcur ID => contribution_status_id
   */
  public static function getRecurringContributionStatus($contribution_ids) {
    if (empty($contribution_ids)) {
      return [];
    }

    $status = [];
    $contribution_id_list = implode(',', $contribution_ids);
    $data = CRM_Core_DAO::executeQuery("
      SELECT rcur.id                     AS rcur_id, 
             rcur.contribution_status_id AS status_id 
      FROM civicrm_contribution_recur rcur
      LEFT JOIN civicrm_contribution contribution ON rcur.id = contribution.contribution_recur_id
      WHERE contribution.id IN ({$contribution_id_list})");
    while ($data->fetch()) {
      $status[$data->rcur_id] = $data->status_id;
    }
    return $status;
  }

  /**
   * Make sure that the submitted recurring contributions have the recorded status
   *
   * @param $desired_status array map contribution_rcur ID => contribution_status_id
   */
  public static function restoreRecurringContributionStatus($desired_status) {
    if (!empty($desired_status)) {
      $recurring_contribution_id_list = implode(',', array_keys($desired_status));
      $current_status = CRM_Core_DAO::executeQuery("
      SELECT rcur.id                     AS rcur_id, 
             rcur.contribution_status_id AS status_id 
      FROM civicrm_contribution_recur rcur
      WHERE rcur.id IN ({$recurring_contribution_id_list})");
      while ($current_status->fetch()) {
        $recurring_contribution_id = $current_status->rcur_id;
        if ($desired_status[$recurring_contribution_id] != $current_status->status_id) {
          // this does NOT have the right status, let's fix that:
          CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution_recur SET contribution_status_id = %1 WHERE id = %2;", [
              1 => [$desired_status[$recurring_contribution_id], 'Integer'],
              2 => [$recurring_contribution_id, 'Integer']]);
          // API doesn't work, silently fails:
          //civicrm_api('ContributionRecur', 'create', [
          //    'id'                     => $recurring_contribution_id,
          //    'contribution_status_id' => $desired_status[$recurring_contribution_id]]);
        }
      }
    }
  }
}
