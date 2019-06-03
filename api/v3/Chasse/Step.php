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
  $spec['journey_id'] = [
    'description' => 'Only process given journey ID. Journey IDs are like: "journey7".',
  ];
  $spec['step'] = [
    'description' => 'Only process given step. Requires journey_id.',
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
  if (!isset($config['journeys'])) {
    throw new API_Exception("No Chassé journey plans are configured. Cannot process.");
  }

  // Stop if given a journey index.
  if (isset($params['journey_index'])) {
    throw new API_Exception("Calling Chasse.step API with journey_index is deprecated. See README.md release notes for v2.");
  }

  // Validate parameters and then pass on to processor.
  $single_journey = NULL;
  if (isset($params['journey_id'])) {
    foreach ($config['journeys'] as $journey) {
      if ($journey['id'] === $params['journey_id']) {
        $single_journey = $journey;
        break;
      }
    }
    if (!$single_journey) {
      throw new API_Exception("Invalid journey_id not found. Perhaps it's been deleted?");
    }
  }

  $step = NULL;
  $found_step_index = NULL;
  if (isset($params['step'])) {
    if (!$single_journey) {
      throw new API_Exception("Missing journey_id. This is required when specifiying 'step'");
    }
    if (empty(trim($params['step']))) {
      throw new API_Exception("Invalid (empty) step code.");
    }
    foreach ($single_journey['steps'] as $step_index => $step) {
      if ($step['code'] === $params['step']) {
        $found_step_index = $step_index;
        break;
      }
    }
    if ($found_step_index === NULL) {
      throw new API_Exception("Invalid 'step' parameter for journey {$journey['id']} (" . $single_journey['name']. ")");
    }
  }

  $chasse_processor = new CRM_Chasse_Processor();
  if ($single_journey) {
    if ($found_step_index !== NULL) {
      // Process single step.
      $chasse_processor->step($journey['id'], $found_step_index);
    }
    else {
      // Process all steps for a journey.
      $chasse_processor->journey($journey['id']);
    }
  }
  else {
    // Process all journeys.
    $chasse_processor->allJourneys();
  }
  return civicrm_api3_create_success([], $params, 'Chasse', 'Step');
}
