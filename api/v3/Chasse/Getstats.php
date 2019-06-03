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

  $chasse = new CRM_Chasse_Processor();

  $stats = CRM_Core_DAO::executeQuery(
    "SELECT ch.{$chasse->step_column_name} AS `step`,
       COUNT(*) AS `all`,
       SUM(COALESCE({$chasse->not_before_column_name}, '') = '' OR $chasse->not_before_column_name <= NOW() ) ready
     FROM {$chasse->table_name} ch
     INNER JOIN civicrm_contact cc ON ch.entity_id = cc.id AND cc.is_deleted = 0
     WHERE ch.{$chasse->step_column_name} IS NOT NULL
     GROUP BY {$chasse->step_column_name}
     ORDER BY {$chasse->step_column_name}"
  );
  $result = [];
  while ($stats->fetch()) {
    $result[$stats->step] = ['all' => (int) $stats->all, 'ready' => (int) $stats->ready];
  }

  return civicrm_api3_create_success($result, $params, 'Chasse', 'GetStats');
}
