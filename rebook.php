<?php
/*-------------------------------------------------------+
| SYSTOPIA Rebook Extension                              |
| Copyright (C) 2017-2019 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| License: AGPLv3, see LICENSE file                      |
+--------------------------------------------------------*/

require_once 'rebook.civix.php';

use CRM_Rebook_ExtensionUtil as E;

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
          'title'  => E::ts('Rebook to contact'),
          'class'  => 'CRM_Rebook_Form_Task_RebookTask',
          'result' => false);
    }
    if (CRM_Core_Permission::check('administer CiviCRM')) {
      $tasks[] = array(
          'title'  => E::ts('Move to contact'),
          'class'  => 'CRM_Rebook_Form_Task_MoveTask',
          'result' => false);
    }
  }
}

function rebook_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  Civi::log()->debug("$op, $objectName, $objectId");
  if ($objectName == 'Contribution' && $op == 'contribution.selector.row') {
    if (CRM_Core_Permission::check('edit contributions')) {
      // add rebook link
      $links[] = [
          'name'  => E::ts("Rebook"),
          'title' => E::ts("Rebook contribution to another contact"),
          'url'   => 'civicrm/rebook/rebook',
          'qs'    => "contributionIds={$objectId}",
          'class' => "small-popup",
      ];
    }
    if (CRM_Core_Permission::check('administer CiviCRM')) {
      // add rebook link
      $links[] = [
          'name'  => E::ts("Move"),
          'title' => E::ts("Move contribution to another contact"),
          'url'   => 'civicrm/rebook/move',
          'qs'    => "contributionIds={$objectId}",
          'class' => "small-popup",
      ];
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
