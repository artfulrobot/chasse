<?php

class CRM_CivirulesActions_ChasseSetStep extends CRM_Civirules_Action
{
  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/civirule/form/action/chasse_set_step', 'rule_action_id='.$ruleActionId);
  }
  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    $action_params = $this->getActionParameters();
    $config = Civi::settings()->get('chasse_config');
    $processor = new CRM_Chasse_Processor;

    // Make a list of steps that exist.
    $steps = [];
    foreach ($config['journeys'] as $journey_id => $journey) {
      foreach ($journey['steps'] as $step) {
        $steps[$step['code']] = TRUE;
      }
    }

    // Get the step code to use and check it still exists in a journey.
    $step_code = $action_params['step_code'];
    if ($step_code === '(none)') {
      $step_code = '';
    }
    elseif (!isset($steps[$step_code])) {
      throw new \Exception("Rule is configured to set step to $step_code, but this no longer exists.");
    }

    // OK to update.
    $step_field = $processor->step_api_field;
    civicrm_api3('Contact', 'create', [
      'id' => $contactId,
      $step_field => $step_code,
    ]);

  }
  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $action_params = $this->getActionParameters();

    $label = "Set Chassé step to '" . $action_params['step_code'] . "'";
    return $label;
  }


}
