<?php

require_once 'chasse.civix.php';
use CRM_Chasse_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function chasse_civicrm_config(&$config) {
  _chasse_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function chasse_civicrm_xmlMenu(&$files) {
  _chasse_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function chasse_civicrm_install() {
  _chasse_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function chasse_civicrm_postInstall() {
  _chasse_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function chasse_civicrm_uninstall() {
  _chasse_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function chasse_civicrm_enable() {
  _chasse_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function chasse_civicrm_disable() {
  _chasse_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function chasse_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _chasse_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function chasse_civicrm_managed(&$entities) {
  _chasse_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function chasse_civicrm_caseTypes(&$caseTypes) {
  _chasse_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function chasse_civicrm_angularModules(&$angularModules) {
  _chasse_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function chasse_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _chasse_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function chasse_civicrm_entityTypes(&$entityTypes) {
  _chasse_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_unsubscribeGroups so that we drop people's journey
 * status if they unsubscribe from the related mailing group.
 *
 * Nb. this was difficult/inefficent/impossible to implement using
 * `hook_civicrm_post` because there's some strange behaviour therein:
 * unsubscribe may issue 'create' (with status "Removed") hooks, *or* 'delete'
 * - but the latter is created whether it's a deletion or a removal.
 *
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_unsubscribeGroups/
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post/
 *
 *
 */
function chasse_civicrm_post($op, $objectName, $objectId, &$objectRef) {

  if ($objectName == 'GroupContact' && ($op == 'create' || $op == 'delete')) {
    // objectId is the group.
    // objectRef is an array of contact_ids.

    // First we need to check whether this 'create' GroupContact thing was 'creating' a 'Removed' record.
    $dao = new CRM_Contact_BAO_GroupContact();
    $dao->group_id = $objectId;
    $dao->contact_id = reset($objectRef);
    $dao->find(TRUE);
    if ($dao->status == 'Removed') {
      // This was a removal. Might need to clear the journey step.
      $chasse_processor = new CRM_Chasse_Processor();
      $chasse_processor->handleUnsubscribe($dao->group_id, $objectRef);
    }
  }
}
/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function chasse_civicrm_navigationMenu(&$menu) {
  _chasse_civix_insert_navigation_menu($menu, 'Mailings', [
    'label'      => E::ts('Chassé Supporter Journeys'),
    'name'       => 'chasse_journeys',
    'url'        => 'civicrm/a/#/chasse',
    'permission' => 'edit message templates', // Seems sensible.
    'operator'   => 'OR',
    'separator'  => 0,
  ]);
  _chasse_civix_navigationMenu($menu);
}

function chasse_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contact') {
    $tasks[] = [
      'title'  => 'Chassé - set journey step',
      'class'  => 'CRM_Chasse_Form_Task_SetStep'
      // 'result' => TRUE, unsure what this does.
    ];
  }
}
/**
 * Implements hook_civicrm_alterAPIPermissions().
 *
 * Specify permissions for API calls required in ang/chasse/Config.js
 *
 * Sets to 'edit message templates' to match chasse_civicrm_navigationMenu
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterAPIPermissions/
 */
function chasse_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {

  $chasseAccessPermissions = ['edit message templates'];

  // Allow users with 'edit message templates' read+write access to Chassé settings.

  if ($entity === 'setting') {

    if (($params['name'] ?? '') === 'chasse_config') {
      $permissions['setting']['getvalue'] = $chasseAccessPermissions;
    }

    // Check 'chasse_config' is the *only* setting to "create" in the parameters for the api call before granting access.
    if (! array_diff ( array_keys($params), array( 'chasse_config', 'check_permissions', 'prettyprint', 'version' ) ) ) {
      $permissions['setting']['create'] = $chasseAccessPermissions;
    }

  }

  // Allow users witih 'edit message templates' to call:
  // - Chasse.getstats
  // - Chasse.step
  $permissions['chasse']['getstats'] = $chasseAccessPermissions;
  $permissions['chasse']['step'] = $chasseAccessPermissions;

  // Note: we do NOT grant access to Chasse.processjourneyschedules
  // as this is designed to be run by cron, which we assume to be
  // run by a higher privileged user.
}
