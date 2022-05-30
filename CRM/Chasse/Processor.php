<?php

/**
 * @file
 * Process chassé journeys, step at a time.
 *
 * (This also provides some helper methods for testing.)
 *
 */
class CRM_Chasse_Processor
{
  /** @var Array cache of chasse_config. */
  protected $config;

  /** @var string table */
  public $table_name;
  /** @var int The custom field id for our step field. */
  public $step_field_id;
  /** @var string column name for our custom step field */
  public $step_column_name;

  /** @var string custom_NNN where NNN is step_field_id */
  public $step_api_field;

  /** @var int The custom field id for our not_before field. */
  public $not_before_field_id;
  /** @var string The custom not_before field's api name. */
  public $not_before_api_field;
  /** @var string The custom not_before field's column name. */
  public $not_before_column_name;
  public function __construct() {
    $this->config = Civi::settings()->get('chasse_config');

    require_once 'CRM/Core/BAO/CustomField.php';
    $this->step_field_id = CRM_Core_BAO_CustomField::getCustomFieldID('chasse_step', 'chasse');
    $this->step_api_field = 'custom_' . $this->step_field_id;
    $this->not_before_field_id = CRM_Core_BAO_CustomField::getCustomFieldID('chasse_not_before', 'chasse');
    $this->not_before_api_field = 'custom_' . $this->not_before_field_id;
    list($this->table_name, $this->step_column_name, $custom_group_id) = CRM_Core_BAO_CustomField::getTableColumnGroup($this->step_field_id);
    list($this->table_name, $this->not_before_column_name, $custom_group_id) = CRM_Core_BAO_CustomField::getTableColumnGroup($this->not_before_field_id);
  }

  /**
   * Process all steps in all journeys.
   *
   * @return Array of values from journey() keyed by journey id.
   */
  public function allJourneys() {
    $return = [];
    foreach ($this->config['journeys'] as $i => $journey) {
      $return[$i] = $this->journey($i);
    }
    return $return;
  }
  /**
   * Process all steps in a journey.
   *
   * @param string $journey_id
   *
   * @return Array like:
   * {
   *   steps: [
   *     { count: N },
   *     ...
   *   ]
   * }
   */
  public function journey($journey_id) {
    $journey = $this->findJourneyById($journey_id);
    $result = ['steps' => []];
    foreach (array_reverse(array_keys($journey['steps'] ?? [])) as $step_index) {
      $result['steps'][$step_index] = ['count' => $this->step($journey_id, $step_index)];
    }
    return $result;
  }
  /**
   * Return the config for the given journey id, or throw.
   *
   * @param string $journey_id
   * @return Array
   */
  public function findJourneyById($journey_id) {
    if (isset($this->config['journeys'][$journey_id])) {
      return $this->config['journeys'][$journey_id];
    }
    throw new \Exception("Journey not found with id '$journey_id'");
  }
  /**
   * Get journey ids of journeys that are scheduled to run now.
   *
   * @param $now timestamp as returned by time() or strtotime(). Used for tests only.
   * @return Array
   */
  public function getScheduledJourneys($now=NULL) {
    if ($now === NULL) {
      $now = time();
    }
    $today = date('N', $now);
    $todays_date = date('j', $now);
    $time_now = date('H:i', $now);

    $scheduled = [];
    foreach ($this->config['journeys'] ?? [] as $journey_id => $journey) {
      if (!isset($journey['schedule'])) {
        // This journey does not have an automation schedule.
        continue;
      }
      if (isset($journey['schedule'])) {
        // This journey is a scheduled one.
        $schedule = $journey['schedule'];

        if (isset($schedule['days']) && !in_array($today, $schedule['days'])) {
          // Not right day.
          continue;
        }

        if (isset($schedule['day_of_month']) && $schedule['day_of_month'] != $todays_date) {
          // Wrong day of month.
          continue;
        }

        if (isset($schedule['time_earliest']) && $time_now < $schedule['time_earliest']) {
          // Too early in the day.
          continue;
        }
        if (isset($schedule['time_latest']) && $time_now > $schedule['time_latest']) {
          // Too late in the day.
          continue;
        }
      }
      $scheduled[] = $journey_id;
    }
    return $scheduled;
  }
  /**
   * Process a single step.
   *
   * @param string $journey_id
   * @param int $step_index
   *
   * @return int number of contacts affected.
   */
  public function step($journey_id, $step_index) {
    $journey = $this->findJourneyById($journey_id);
    $step = $journey['steps'][$step_index] ?? NULL;
    if (!$step) {
      throw new \Exception("Invalid step index $step_index in journey $journey_id");
    }
    //Civi::log()->info("Chasse processing $journey_id step $step[code]");

    // Check: if there aren't any contacts, don't do anything!
    $count = (int) CRM_Core_DAO::executeQuery("
        SELECT COUNT(*)
        FROM $this->table_name ch
        INNER JOIN civicrm_contact cc ON ch.entity_id = cc.id AND cc.is_deleted = 0
        WHERE $this->step_column_name = %1
          AND (COALESCE({$this->not_before_column_name}, '') = '' OR {$this->not_before_column_name} <= NOW())
        ",
      [1 => [$step['code'], 'String']])
      ->fetchValue();
    if (!$count) {
      return 0;
    }

    // Put the contacts in a group.
    $group_id = $this->populateJourneyGroup($journey_id, $step['code']);

    if ($step['send_mailing']) {
      $this->sendMailing($step['send_mailing'], $journey, $step, $group_id);
    }

    if ($step['add_to_group']) {
      $this->addToGroup($journey['mailing_group'], $group_id);
    }

    $this->updateStep($step, $group_id);
    return $count;
  }

  /**
   * Send a mailing to contacts on with a particular journey code.
   *
   * @param int $msg_template_id
   * @param array $journey The config for this journey.
   * @param array $step. The step we're processing.
   * @param int $group_id. The group that contains the contacts to mail.
   *
   * @return int ID of newly created mailing.
   */
  public function sendMailing($msg_template_id, $journey, $step, $group_id) {

    $tpl = civicrm_api3('MessageTemplate', 'getsingle', ['id' => $msg_template_id]);

    // Mosaico insists on [unsubscribe_link] and [show_link] instead of CiviCRM's tokens, so replace that now.
    $tpl['msg_html'] = strtr($tpl['msg_html'], [
      '[unsubscribe_link]' => '{action.unsubscribeUrl}',
      '[show_link]'        => '{mailing.viewUrl}',
    ]);

    $unsubscribe_group = $journey['mailing_group'];


    // Extract from address fields from the step.
    $result = civicrm_api3('OptionValue',  'getvalue',
      ['return'=> "label", 'value'=> $step['mail_from'], 'option_group_id'=> 'from_email_address']);
    if (preg_match('/^"([^"]+)"\s+<([^>]+)>$/', $result, $_)) {
      $from_name = $_[1];
      $from_mail = $_[2];
    }
    else {
      throw new \Exception("Invalid From email address on journey $journey[name], step $step[code], (from email address #$journey[mail_from])");
    }

    // Domain contact
    $domain_contact = CRM_Core_BAO_Domain::getDomain()->contact_id;

    $mailing = civicrm_api3('Mailing', 'create', [
      'sequential' => 1,
      'name' => $tpl['msg_title'],
      'msg_template_id' => $msg_template_id,
      //'replyto_email'
      'groups' => [
        'include' => [$group_id],
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
      'campaign_id' => $journey['campaign_id'] ?? null,

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
   * Add all the contacts from the given group_id to the mailing group.
   *
   * @var int $mailing_group Group ID
   * @var int $group_id of the temporary group for this journey.
   */
  public function addToGroup($mailing_group, $group_id) {

    $dao = new CRM_Contact_DAO_GroupContact();
    $dao->group_id = $group_id;
    $dao->status = 'Added';
    $contact_ids = [];
    if ($dao->find()) {
      while ($dao->fetch()) {
        $contact_ids[] = $dao->contact_id;
      }
    }
    //Civi::log()->info("chassetest: will add contacts " . implode(',', $contact_ids));

    CRM_Contact_BAO_GroupContact::bulkAddContactsToGroup($contact_ids, $mailing_group);
  }

  /**
   * Update all contacts from one step to another.
   *
   * @var string $step config array.
   * @var int $group_id CiviCRM group ID.
   */
  public function updateStep($step, $group_id) {

    if (empty($step['next_code'])) {
      // We need to clear the step and not_before date.

      $sql = "UPDATE $this->table_name chasse
        INNER JOIN civicrm_group_contact gc
          ON chasse.entity_id = gc.contact_id
             AND gc.group_id = %1
             AND gc.status = 'Added'
        SET $this->step_column_name = NULL,
            $this->not_before_column_name = NULL
        WHERE $this->step_column_name = %2;";

      $contacts = CRM_Core_DAO::executeQuery($sql, [
        1 => [$group_id, 'Integer'],
        2 => [$step['code'], 'String'],
      ]);
    }
    else {
      if (empty($step['interval'])) {
        // We're not using the not_before field.
        $interval = 'NULL';
      }
      else {
        // We are using not_before, we need to add the interval.
        $this->assertSafeInterval($step);
        $interval = "COALESCE($this->not_before_column_name, NOW()) + INTERVAL " . $step['interval'];
      }
      $sql = "UPDATE $this->table_name chasse
        INNER JOIN civicrm_group_contact gc
          ON chasse.entity_id = gc.contact_id
             AND gc.group_id = %1
             AND gc.status = 'Added'
        SET $this->step_column_name = %2,
            $this->not_before_column_name = $interval
        WHERE $this->step_column_name = %3;";

      $contacts = CRM_Core_DAO::executeQuery($sql, [
        1 => [$group_id, 'Integer'],
        2 => [$step['next_code'], 'String'],
        3 => [$step['code'], 'String'],
      ]);
    }
  }

  /**
   * Returns the step config for a given journey ID and step code.
   *
   * @param string $journey_id
   * @param string $step_code
   *
   * @return array
   */
  public function getStep($journey_id, $step_code) {
    $journey = $this->findJourneyById($journey_id);
    foreach ($journey['steps'] ?? [] as $step) {
      if ($step['code'] === $step_code) {
        return $step;
      }
    }
    throw new \Exception("Step '$step_code' not found in journey '$journey_id'");
  }
  /**
   * Helper / DRY function.
   *
   * @return array keyed by step codes whose values are labels
   */
  public function getStepCodeOptions() {
    $steps = [];
    foreach ($this->config['journeys'] as $journey) {
      foreach ($journey['steps'] as $step) {
        $steps[$step['code']] = $journey['name'] . ': step "' . $step['code'] . '"';
      }
    }
    return $steps;
  }
  /**
   * Clear the journey field for contacts if they have been removed from the group.
   *
   * @var int $group_id
   * @var Array $contact_ids
   */
  public function handleUnsubscribe($group_id, $contact_ids) {

    $steps_to_clear = [];
    foreach ($this->config['journeys'] as $journey_id=>$journey) {
      if ($journey['mailing_group'] == $group_id) {
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
    $contact_ids = implode(',', array_map(function($_) { return (int)$_; }, $contact_ids));
    $steps_to_clear = CRM_Core_DAO::escapeStrings($steps_to_clear);
    if (!$contact_ids) {
      // Odd.
      return;
    }
    $sql = "UPDATE $this->table_name SET $this->step_column_name = NULL "
      . "WHERE entity_id IN ($contact_ids) AND $this->step_column_name IN($steps_to_clear)";
    CRM_Core_DAO::executeQuery($sql);

  }
  /**
   * Are we already processing other jobs?
   *
   * @return bool TRUE if lock exists.
   */
  public function lockExists() {
    $locks = Civi::settings()->get('chasse_locks');
    if (!$locks) {
      $locks = [];
    }
    return isset($locks['global']);
  }
  /**
   * Try to obtain a lock.
   *
   * @return bool TRUE if lock granted.
   */
  public function attemptToLock() {
    $locks = Civi::settings()->get('chasse_locks');
    if (!$locks) {
      $locks = [];
    }
    if (isset($locks['global'])) {
      // Already locked.
      return FALSE;
    }
    // Looks ok to lock.
    $locks['global'] = date('Y-m-d H:i:s');
    Civi::settings()->set('chasse_locks', $locks);
    return TRUE;
  }
  /**
   * Release lock.
   */
  public function releaseLock() {
    $locks = Civi::settings()->get('chasse_locks');
    if (!$locks) {
      $locks = [];
    }
    unset($locks['global']);
    Civi::settings()->set('chasse_locks', $locks);
  }
  /**
   * Populates the (hidden) group for this journey.
   *
   * @param string $journey_id
   * @param string $step_code
   *
   * @return int Group ID.
   *
   */
  protected function populateJourneyGroup($journey_id, $step_code) {
    $group_id = $this->getEmptyJourneyGroup($journey_id);
    // Add all the contacts to this group.
    $contact_ids = CRM_Core_DAO::executeQuery("
        SELECT ch.entity_id contact_id
        FROM $this->table_name ch
        INNER JOIN civicrm_contact cc ON ch.entity_id = cc.id AND cc.is_deleted = 0
        WHERE $this->step_column_name = %1
          AND (COALESCE({$this->not_before_column_name}, '') = '' OR {$this->not_before_column_name} <= NOW())
        ",
      [1 => [$step_code, 'String']])->fetchMap('contact_id', 'contact_id');

    //Civi::log()->info("Chasse: populateJourneyGroup $journey_id:$step_code contacts: ". implode(', ', $contact_ids));
    CRM_Contact_BAO_GroupContact::bulkAddContactsToGroup($contact_ids, $group_id);
    return $group_id;
  }
  /**
   * Ensure we have an empty, hidden group for this journey. It's used internally as a
   * cache of contacts.
   *
   * @param string $journey_id
   * @return int CiviCRM group ID.
   */
  protected function getEmptyJourneyGroup($journey_id) {
    $title = "Chasse working group for $journey_id";
    $groups = civicrm_api3('Group', 'get', ['title' => $title, 'sequential' => 1]);
    if ($groups['count'] == 1) {
      $group_id = $groups['values'][0]['id'];
      // Group existed, delete it now.
      civicrm_api3('Group', 'delete', ['id' => $group_id]);
    }
    // Now create a new group.
    $params = array(
      'title'       => $title,
      'description' => 'This was created by the Chassé extension. Do not add or remove contacts to it as it gets automatically populated/emptied.',
      'visibility'  => 'User and User Admin Only',
      'is_active'   => 1,
      'is_hidden'   => 1, // This is important!
    );
    $result = civicrm_api3('Group', 'create', $params);
    return (int) $result['id'];
  }
  /**
   * A step's interval value, when given, must be SQL-safe.
   *
   * This function throws an \InvalidArgumentException if it's not.
   *
   * @param array $step
   * @throws \InvalidArgumentException
   * @return void
   */
  public function assertSafeInterval($step) {
    if (!preg_match('/^[\d]{1,3} (HOUR|DAY|WEEK|MONTH)$/', $step['interval'] ?? '')) {
      throw new \InvalidArgumentException("Step $step[code] has invalid interval.");
    }
  }
}
