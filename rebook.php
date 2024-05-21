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
//  Civi::log()->debug("$op, $objectName, $objectId");
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

function rebook_civicrm_navigationMenu(array &$menu) {
  _rebook_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => E::ts('Rebook Settings'),
    'name' => 'rebook_settings',
    'url' => 'civicrm/admin/setting/rebook',
    'permission' => 'administer CiviCRM',

  ]);
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
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function rebook_civicrm_install() {
  _rebook_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function rebook_civicrm_enable() {
  _rebook_civix_civicrm_enable();
}
