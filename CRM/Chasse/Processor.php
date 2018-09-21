<?php

/**
 * @file
 * Process chassé journeys, step at a time.
 *
 */
class CRM_Chasse_Processor
{
  /** @var Array cache of chasse_config. */
  protected $journeys;

  /** @var int The custom field id for our step field. */
  public $step_field_id;
  /** @var string table */
  public $table_name;
  /** @var string column name for our custom step field */
  public $column_name;

  /** @var array keys are step codes, value is the smart group ID */
  protected $smart_group_cache = [];
  public function __construct() {
    $this->journeys = Civi::settings()->get('chasse_config');

    require_once 'CRM/Core/BAO/CustomField.php';
    $this->step_field_id = CRM_Core_BAO_CustomField::getCustomFieldID('chasse_step', 'chasse');
    list($this->table_name, $this->column_name, $custom_group_id) = CRM_Core_BAO_CustomField::getTableColumnGroup($this->step_field_id);
  }

  /**
   * Process all steps in all journeys.
   */
  public function allJourneys() {
    foreach ($this->journeys as $i => $journey) {
      $this->journey($i);
    }
  }
  /**
   * Process all steps in a journey.
   *
   * @param int $journey_index
   */
  public function journey($journey_index) {
    foreach (array_reverse(array_keys($this->journeys[$journey_index]['steps'] ?? [])) as $step_index) {
      $this->step($journey_index, $step_index);
    }
  }
  /**
   * Process a single step.
   *
   * @param int $journey_index
   * @param int $step_index
   */
  public function step($journey_index, $step_index) {
    $step = $this->journeys[$journey_index]['steps'][$step_index];
    if (!$step) {
      throw new \Exception("Invalid step index $step_index in journey $journey_index");
    }

    // Check: if there aren't any contacts, don't do anything!
    $count = (int) CRM_Core_DAO::executeQuery("SELECT COUNT(*) FROM $this->table_name WHERE $this->column_name = %1", [1 => [$step['code'], 'String']])->fetchValue();
    if (!$count) {
      return;
    }

    if ($step['send_mailing']) {
      $this->sendMailing($step['send_mailing'], $this->journeys[$journey_index], $step['code']);
    }

    if ($step['add_to_group']) {
      $this->addToGroup($this->journeys[$journey_index]['mailing_group'], $step['code']);
    }

    $this->updateStep($step['code'], $step['next_code']);
  }

  /**
   * Send a mailing to contacts on with a particular journey code.
   *
   * @param int $msg_template_id
   * @param string $code.
   * @param int $unsubscribe_group
   *
   * @return int ID of newly created mailing.
   */
  public function sendMailing($msg_template_id, $journey, $step_code) {
    $tpl = civicrm_api3('MessageTemplate', 'getsingle', ['id' => $msg_template_id]);
    $unsubscribe_group = $journey['mailing_group'];
    $smart_group_id = $this->getSmartGroupForStep($step_code);

    // Extract from address fields.
    $result = civicrm_api3('OptionValue',  'getvalue',
      ['return'=> "label", 'value'=> $journey['mail_from'], 'option_group_id'=> 'from_email_address']);
    if (preg_match('/^"([^"]+)"\s+<([^>]+)>$/', $result, $_)) {
      $from_name = $_[1];
      $from_mail = $_[2];
    }
    else {
      throw new \Exception("Invalid From email address on journey $journey[name], step $step_code, (from email address #$journey[mail_from])");
    }

    // Domain contact
    $domain_contact = CRM_Core_BAO_Domain::getDomain()->contact_id;

    $mailing = civicrm_api3('Mailing', 'create', [
      'sequential' => 1,
      'name' => $tpl['msg_title'],
      'msg_template_id' => $msg_template_id,
      //'replyto_email'
      'groups' => [
        'include' => [$smart_group_id],
        'exclude' => [],
        'base' => [$unsubscribe_group],
      ],
      'from_name' => $from_name,
      'from_email' => $from_mail,
      'header_id' => '',
      'footer_id' => '',
      'created_id' => $domain_contact,
      'scheduled_id' => $domain_contact,
      'scheduled_date' => date('Y-m-d H:i:s'),
      'approval_date' => date('Y-m-d H:i:s'),
      'body_html' => $tpl['msg_html'],
      'subject' => $tpl['msg_subject'],

      //'template_type' => $templateTypes[0]['name'],
      //'template_options' => array('nonce' => 1),
      //'subject' => "Auto test 1",
      //'body_html' => "",
      //'body_text' => "",
      //'mailings' => array(
      //'include' => array(),
      //'exclude' => array(),
      //),
    ]);

    return $mailing['id'];
  }

  /**
   * We require a smart group that returns all contacts in a given step.
   *
   * If this doesn't exist we create it.
   *
   * Note: We set `is_hidden` = 1.
   * Without this, contacts will see the name of the smart group if they click
   * the unsubscribe link.  This could be unsuitable, unhelpful or confusing.
   * As well as this, without hiding the smart group the contact who
   * unsubscribes is put in a state of Removed against the smart group, which
   * could interfere with future journeys.
   *
   *
   */
  public function getSmartGroupForStep($step_code) {
    if (isset($this->smart_group_cache[$step_code])) {
      // Done the work before, return from cache.
      return $this->smart_group_cache[$step_code];
    }

    $name = "Contacts on chassé journey step $step_code";
    $groups = civicrm_api3('Group', 'get', ['title' => $name, 'sequential' => 1]);
    if ($groups['count'] == 1) {
      $group_id = $groups['values'][0]['id'];
    }
    else {
      // Need to create a smart group.
      $params = array(
        'title'       => $name,
        'description' => 'This was created by the Chassé extension to identify contacts at journey stage ' . $step_code,
        'visibility'  => 'User and User Admin Only',
        'is_active'   => 1,
        'is_hidden'   => 1, // This is important!
        'formValues'  => ["custom_$this->step_field_id" => $step_code],
      );

      $group = CRM_Contact_BAO_Group::createSmartGroup($params);
      $group_id = $group->id;
    }

    // Make sure we're bang up to date.
    CRM_Contact_BAO_GroupContactCache::clearGroupContactCache($group_id);
    CRM_Contact_BAO_GroupContactCache::check([$group_id]);
    // Store this in the cache (so we don't keep clearing and recreating it within one run).
    $this->smart_group_cache[$step_code] = $group_id;

    return $group_id;
  }

  /**
   * Add all the contacts on the given step to the mailing_group.
   *
   * @var int $mailing_group Group ID
   * @var string $step_code
   */
  public function addToGroup($mailing_group, $step_code) {

    // Get all the contacts with this step code.
    $smart_group_id = $this->getSmartGroupForStep($step_code);

    $sql = "SELECT entity_id FROM $this->table_name WHERE $this->column_name = %1;";
    $contacts = array_values(CRM_Core_DAO::executeQuery($sql, [1 => [$step_code, 'String']])
      ->fetchMap('entity_id', 'entity_id'));

    CRM_Contact_BAO_GroupContact::addContactsToGroup($contacts, $mailing_group);
  }

  /**
   * Update all contacts from one step to another.
   *
   * @var string $step_code
   * @var string $new_step_code
   */
  public function updateStep($step_code, $new_step_code) {

    if (empty($new_step_code)) {
      $sql = "UPDATE $this->table_name SET $this->column_name = NULL WHERE $this->column_name = %1;";
      $contacts = CRM_Core_DAO::executeQuery($sql, [
        1 => [$step_code, 'String'],
      ]);
    }
    else {
      $sql = "UPDATE $this->table_name SET $this->column_name = %1 WHERE $this->column_name = %2;";
      $contacts = CRM_Core_DAO::executeQuery($sql, [
        1 => [$new_step_code, 'String'],
        2 => [$step_code, 'String'],
      ]);
    }

    // Update smart groups
    foreach ([$step_code, $new_step_code] as $_) {
      if (!empty($_)) {
        unset($this->smart_group_cache[$_]);
        $this->getSmartGroupForStep($_);
      }
    }
  }

  public function getStep($journey_index, $step_code) {
    foreach ($this->journeys[$journey_index]['steps'] ?? [] as $step) {
      if ($step['code'] === $step_code) {
        return $step;
      }
    }
    throw new \Exception("Step '$step_code' not found");
  }
  /**
   * Clear the journey field for contacts if they click unsubscribe.
   *
   * This does not work because the hook is called before the user has confirmed they wish to unsubscribe!
   *
   * @var Array $group_ids
   * @var Array $contact_ids
   */
  public function handleUnsubscribe($group_ids, $contact_id) {

    $steps_to_clear = [];
    foreach ($this->journeys as $journey_index=>$journey) {
      if (in_array($journey['mailing_group'], $group_ids)) {
        foreach ($journey['steps'] as $step) {
          $steps_to_clear[] = $step['code'];
        }
      }
    }
    if (!$steps_to_clear) {
      // OK, nothing to do.
      return;
    }

    // Stuff to do.
    $contact_id = (int)$contact_id;
    $steps_to_clear = CRM_Core_DAO::escapeStrings($steps_to_clear);
    if (!$contact_id) {
      // Odd.
      return;
    }
    $sql = "UPDATE $this->table_name SET $this->column_name = NULL "
      . "WHERE entity_id = $contact_id AND $this->column_name IN($steps_to_clear)";
    CRM_Core_DAO::executeQuery($sql);

  }
}
