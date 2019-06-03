<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Chasse.Getstats API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_Chasse_Base extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /** These hold IDs of fixtures. */
  public $mailing_group;
  public $msg_tpl_1;
  public $msg_tpl_2;
  public $from_id;
  public $contact_fixtures = [
		[
			'contact_type' => 'Individual',
			'first_name' => 'Wilma',
			'last_name' => 'Flintstone',
			'email' => 'wilma.flintstone@example.com',
		],
		[
			'contact_type' => 'Individual',
			'first_name' => 'Betty',
			'last_name' => 'Rubble',
			'email' => 'betty.rubble@example.com',
		],
		[
			'contact_type' => 'Individual',
			'first_name' => 'Pebbles',
			'last_name' => 'Flintstone',
			'email' => 'pebbles@example.com',
		],
	];

  /**
   * Assert stats results
   */
  public function assertStats($expected) {
    $result = civicrm_api3('Chasse', 'Getstats', []);
    $this->assertEquals($expected, $result['values']);
  }

  /**
   * Assert that a contact is not in a CiviCRM group. (not smart groups)
   *
   * @param int $contact_id
   * @param int $group_id
   * @param string $message
   */
  public function assertNotInGroup($contact_id, $group_id, $message) {
    $result = civicrm_api3('GroupContact', 'get', [
        'contact_id' => $contact_id,
        'group_id'   => $group_id,
        'status'     => 'Added',
      ]);
    $this->assertEquals(0, $result['count'], $message);
  }
  /**
   * Assert that a contact is in a CiviCRM group. (not smart groups)
   *
   * @param int $contact_id
   * @param int $group_id
   * @param string $message
   */
  public function assertInGroup($contact_id, $group_id, $message) {
    $result = civicrm_api3('GroupContact', 'get', [
        'contact_id' => $contact_id,
        'group_id'   => $group_id,
        'status'     => 'Added',
      ]);
    $this->assertEquals(1, $result['count'], $message);
  }
  /**
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   * See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   */
  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * The setup() method is executed before the test is executed (optional).
   */
  public function setUp() {
    parent::setUp();

    // Create a Mailing group.
    $mailing_group = civicrm_api3('group', 'create', [
      'title' => "chasse api test mailing list",
      'name' => "chasse_test",
      'group_type' => "Mailing List",
    ]);
    $this->mailing_group = $mailing_group['id'];

    // Create MSg tpls.
    $result = civicrm_api3('MessageTemplate', 'create', [
      'msg_title' => "chasse_mailing_1",
      'msg_subject' => "chasse_mailing_1",
      'msg_html' => "<p>chasse_mailing_1</p>{action.unsubscribeUrl} {domain.address}",
    ]);
    $this->msg_tpl_1 = $result['id'];

    $result = civicrm_api3('MessageTemplate', 'create', [
      'msg_title' => "chasse_mailing_2",
      'msg_subject' => "chasse_mailing_2",
      'msg_html' => "<p>chasse_mailing_2</p>{action.unsubscribeUrl} {domain.address}",
    ]);
    $this->msg_tpl_2 = $result['id'];


    // From address.
    $result = civicrm_api3('OptionValue', 'get', [
      //'return'          => "value",
      'option_group_id' => "from_email_address",
      'value' => 1,
      'is_default'      => 1,
    ]);
    if ($result['count'] > 1) {
      // For some reason the test database comes with two identically value-ed from addresses.
      $ok = array_shift($result['values']);
      foreach ($result['values'] as $_) {
        civicrm_api3('OptionValue', 'delete', ['id' => $_['id']]);
      }
    }
    $this->from_id = 1;

		// Create contacts.
		foreach ($this->contact_fixtures as $idx => $_) {
      $result = civicrm_api3('Contact', 'create', $_);
			$this->assertGreaterThan(0, $result['id']);
      $this->contact_fixtures[$idx]['id'] = $result['id'];
		}

  }

  /**
   * The tearDown() method is executed after the test was executed (optional)
   * This can be used for cleanup.
   */
  public function tearDown() {
    parent::tearDown();
  }

}

