<?php
use CRM_Chasse_ExtensionUtil as E;

/**
 * Chasse.Step API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_chasse_Step_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
  $spec['journey_index'] = [
    'description' => 'Only process given journey. Index 0 is the first one.',
  ];
  $spec['step'] = [
    'description' => 'Only process given step. Requires journey_index.',
  ];
}

/**
 * Chasse.Step API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_chasse_Step($params) {

  $config = Civi::settings()->get('chasse_config');
  if (!$config) {
    throw new API_Exception("No Chassé journey plans are configured. Cannot process.");
  }

  // Validate parameters and then pass on to processor.
  $journey_index = NULL;
  if (isset($params['journey_index'])) {
    $journey_index = (int) $params['journey_index'];
    if ($journey_index < 0 || $journey_index >= count($config)) {
      throw new API_Exception("Invalid journey_index");
    }
  }

  $step = NULL;
  $found_step_index = NULL;
  if (isset($params['step'])) {
    if ($journey_index === NULL) {
      throw new API_Exception("Missing journey_index. This is required when specifiying 'step'");
    }
    if (empty(trim($params['step']))) {
      throw new API_Exception("Invalid (empty) step code.");
    }
    foreach ($config[$journey_index]['steps'] as $step_index => $step) {
      if ($step['code'] === $params['step']) {
        $found_step_index = $step_index;
        break;
      }
    }
    if ($found_step_index === NULL) {
      throw new API_Exception("Invalid 'step' parameter for journey $journey_index (" . $config[$journey_index]['name']. ")");
    }

  }

  $chasse_processor = new CRM_Chasse_Processor();
  if ($journey_index !== NULL) {
    if ($found_step_index !== NULL) {
      $chasse_processor->step($journey_index, $found_step_index);
    }
    else {
      $chasse_processor->journey($journey_index);
    }
  }
  else {
    $chasse_processor->allJourneys($journey_index);
  }
  return civicrm_api3_create_success([], $params, 'Chasse', 'Step');
}
