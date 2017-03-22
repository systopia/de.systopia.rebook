<?php
/*-------------------------------------------------------+
| SYSTOPIA Rebook Extension                              |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

require_once 'rebook.civix.php';


/**
* Add an action for creating donation receipts after doing a search
*
* @param string $objectType specifies the component
* @param array $tasks the list of actions
*
* @access public
*/
function rebook_civicrm_searchTasks($objectType, &$tasks) {
  // add REBOOK task to contribution list
  if ($objectType == 'contribution') {
    if (CRM_Core_Permission::check('edit contributions')) {
      $tasks[] = array(
          'title'  => ts('Rebook to contact', array('domain' => 'de.systopia.rebook')),
          'class'  => 'CRM_Rebook_Form_Task_RebookTask',
          'result' => false);
    }
  }
}


/**
 *  Add rebook actions is contribution search result
 */
function rebook_civicrm_searchColumns($objectName, &$headers,  &$values, &$selector) {
  if ($objectName == 'contribution') {
    // only offer rebook only if the user has the correct permissions
    if (CRM_Core_Permission::check('edit contributions')) {
      $contribution_status_complete = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');
      $title = ts('Rebook', array('domain' => 'de.systopia.rebook'));
      $url = CRM_Utils_System::url('civicrm/rebook/rebook', "contributionIds=__CONTRIBUTION_ID__");
      $action = "<a title=\"$title\" class=\"action-item action-item\" href=\"$url\">$title</a>";

      // add 'rebook' action link to each row
      foreach ($values as $rownr => $row) {
        $contribution_status_id = $row['contribution_status_id'];
        // ... but only for completed contributions
        if ($contribution_status_id==$contribution_status_complete) {
          // this contribution is o.k. => add the rebook action
          // FIXME: use hook instead
          $contribution_id = $row['contribution_id'];
          $this_action = str_replace('__CONTRIBUTION_ID__', $contribution_id, $action);
          $values[$rownr]['action'] = str_replace('</span>', $this_action.'</span>', $row['action']);
        }
      }
    }
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function rebook_civicrm_config(&$config) {
  _rebook_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function rebook_civicrm_xmlMenu(&$files) {
  _rebook_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function rebook_civicrm_install() {
  _rebook_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function rebook_civicrm_uninstall() {
  _rebook_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function rebook_civicrm_enable() {
  _rebook_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function rebook_civicrm_disable() {
  _rebook_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function rebook_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _rebook_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function rebook_civicrm_managed(&$entities) {
  _rebook_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function rebook_civicrm_caseTypes(&$caseTypes) {
  _rebook_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function rebook_civicrm_angularModules(&$angularModules) {
_rebook_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function rebook_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _rebook_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
