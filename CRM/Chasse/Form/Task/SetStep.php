<?php

use CRM_Chasse_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Chasse_Form_Task_SetStep extends CRM_Contact_Form_Task {

  public function buildQuickForm() {

    $processor = new CRM_Chasse_Processor;
    $this->add(
      'select', // field type
      'new_step', // field name
      'Set Chassé journey step to', // field label
      $processor->getStepCodeOptions(),
      TRUE // is required
    );

    // add form elements
    $this->add(
      'checkbox', // field type
      'only_if_blank', // field name
      E::ts('Only set step if not currently on a journey')
    );

    $this->addRadio(
      'set_delay', // field name
      '',
      [
        'date'      => E::ts('Do not process step until'),
        'immediate' => E::ts('No delay'),
        'leave'     => E::ts('Leave delay as it was'),
      ]
    );
    $this->add(
      'datepicker',
      'not_before',
      E::ts('Do not process until'),
      [], // attrs
      FALSE, // required
      [] // datepicker config.
    );

    $this->setDefaults([
      'only_if_blank' => 1,
      'set_delay' => 'date',
      'not_before' => '',
    ]);

    // add radios

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
   // public $_contactIds;


    // Update chasse status for given contacts.
    $processor = new CRM_Chasse_Processor;
    $params = [ ($processor->step_api_field) => $values['new_step'] ];
    if ($values['set_delay'] === 'date') {
      $params[$processor->not_before_api_field] = $values['not_before'];
    }
    elseif ($values['set_delay'] === 'immediate') {
      $params[$processor->not_before_api_field] = '';
    }

    // Loop contacts and update.
    $no_clobber = !empty($values['only_if_blank']);
    if ($no_clobber) {
      $contacts = civicrm_api3('Contact', 'get',
        [
          'id' => ['IN' => $this->_contactIds],
          'return' => [ $processor->step_api_field ]
        ]);
    }
    $altered = 0;
    foreach ($this->_contactIds as $contact_id) {
      if ($no_clobber && !empty($contacts['values'][$contact_id][$processor->step_api_field])) {
        // We've been told not to clobber existing data.
        continue;
      }
      $altered++;
      $params['id'] = $contact_id;
      civicrm_api3('Contact', 'create', $params);
    }

    CRM_Core_Session::setStatus(E::ts('%1 Contact(s) updated', [1 => $altered]), '', 'success');
    parent::postProcess();
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

}
