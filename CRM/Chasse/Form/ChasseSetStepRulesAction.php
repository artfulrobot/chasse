<?php

use CRM_Chasse_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Chasse_Form_ChasseSetStepRulesAction extends CRM_CivirulesActions_Form_Form {
  public function buildQuickForm() {

    $steps = ['(none)' => 'None (remove from journey)'];
    $config = Civi::settings()->get('chasse_config');
    foreach ($config['journeys'] as $journey_id => $journey) {
      foreach ($journey['steps'] as $step) {
        $steps[$step['code']] = $step['code'] . " (" . $journey['name'] . ")";
      }
    }

    // add form elements
    $this->add(
      'select', // field type
      'step_code', // field name
      'Set step code to', // field label
      $steps,
      TRUE // is required
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));
    
    // Add rule action ID
    $this->add('hidden', 'rule_action_id');

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->ruleAction->action_params);
    if (!empty($data['step_code'])) {
      $defaultValues['step_code'] = $data['step_code'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['step_code'] = $this->_submitValues['step_code'];
    /*
    if ($this->_submitValues['type'] == 0) {
      $data['sub_type'] = array($this->_submitValues['subtype']);
    } else {
      $data['sub_type'] = $this->_submitValues['subtypes'];
    }
     */

    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }
}
