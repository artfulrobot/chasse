<?php
use CRM_Chasse_ExtensionUtil as E;

/**
 * Chasse.Getstats API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_chasse_Getstats_spec(&$spec) {
}

/**
 * Chasse.Getstats API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_chasse_Getstats($params) {
  require_once 'CRM/Core/BAO/CustomField.php';
  $customFieldID = CRM_Core_BAO_CustomField::getCustomFieldID('chasse_step', 'chasse');
  list($table, $column, $custom_group_id) = CRM_Core_BAO_CustomField::getTableColumnGroup($customFieldID);
  $stats = CRM_Core_DAO::executeQuery(
    "SELECT $column AS `step`, COUNT(*) AS contacts
     FROM $table
     WHERE $column IS NOT NULL
     GROUP BY $column"
  )->fetchMap('step', 'contacts');
  foreach ($stats as &$_) { $_ = (int) $_; }

  return civicrm_api3_create_success($stats, $params, 'Chasse', 'GetStats');
}
