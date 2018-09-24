<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Chasse.Step API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_Chasse_StepTest  extends api_v3_Chasse_Base
{
  /**
   * Check performing one step works.
   */
  public function testStepS2() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    // Set a step for a couple of contacts.
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[0]['id'],
      $chasse_processor->step_api_field => 'S1',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[1]['id'],
      $chasse_processor->step_api_field => 'S2',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[2]['id'],
      $chasse_processor->step_api_field => 'S2',
    ]);

    // Sanity check.
    $this->assertStats(['S1' => 1, 'S2' => 2]);

    $result = civicrm_api3('Chasse', 'step', ['journey_index' => 0, 'step' => 'S2']);
    $this->assertEquals(0, $result['is_error']);
    // The stats should now show no one in S2 but S1 should be the same.
    $this->assertStats(['S1' => 1]);
    // We should have the 2 people on the mailing group now.
    foreach ([1, 2] as $i) {
      $result = civicrm_api3('GroupContact', 'getsingle', [
        'contact_id' => $this->contact_fixtures[$i]['id'],
        'group_id' => $this->mailing_group,
        'status' => 'Added',
      ]);
    }
    // We should have a mailing ready.
    $result = civicrm_api3('Mailing', 'get', [
      'msg_template_id' => $this->msg_tpl_2,
      'body_html' => ['LIKE' => '%chasse_mailing_2%'],
      'scheduled_date' => ['IS NOT NULL' => 1],
      'approved_date' => ['IS NOT NULL' => 1],
    ]);
    $this->assertEquals(1, $result['count']);

    // The contacts in that mailing should be....

    // We should have a mailing ready.
    $result = civicrm_api3('Mailing', 'get', [
      'msg_template_id' => $this->msg_tpl_2,
      'body_html' => ['LIKE' => '%chasse_mailing_2%'],
      'scheduled_date' => ['IS NOT NULL' => 1],
      'approved_date' => ['IS NOT NULL' => 1],
    ]);
    $this->assertEquals(1, $result['count']);

    // The mailing should have 2 recipients.
    $result = civicrm_api3('MailingRecipients', 'get', [ 'mailing_id' => $result['id'] ]);
    $this->assertEquals(2, $result['count']);

  }

  /**
   * Check performing one journey
   */
  public function testJourney0() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    // Set a step for a couple of contacts.
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[0]['id'],
      $chasse_processor->step_api_field => 'S1',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[1]['id'],
      $chasse_processor->step_api_field => 'S2',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[2]['id'],
      $chasse_processor->step_api_field => 'S2',
    ]);

    // Sanity check.
    $this->assertStats(['S1' => 1, 'S2' => 2]);

    // Step the whole journey.
    $result = civicrm_api3('Chasse', 'step', ['journey_index' => 0]);
    $this->assertEquals(0, $result['is_error']);
    // The stats should now show no one in S2 but one person in S1.
    $this->assertStats(['S2' => 1]);
    // We should NOT have contact 0 on the mailing group.
    $result = civicrm_api3('GroupContact', 'get', [
        'contact_id' => $this->contact_fixtures[0]['id'],
        'group_id' => $this->mailing_group,
        'status' => 'Added',
      ]);
    $this->assertEquals(0, $result['count']);

    // The contacts in that mailing should be....

    // We should have a mailing ready for 1.
    $result = civicrm_api3('Mailing', 'get', [
      'msg_template_id' => $this->msg_tpl_1,
      'body_html' => ['LIKE' => '%chasse_mailing_1%'],
      'scheduled_date' => ['IS NOT NULL' => 1],
      'approved_date' => ['IS NOT NULL' => 1],
    ]);
    $this->assertEquals(1, $result['count']);

    // The mailing should have 1 recipients.
    $result = civicrm_api3('MailingRecipients', 'get', [ 'mailing_id' => $result['id'] ]);
    $this->assertEquals(1, $result['count']);

  }

  /**
   * Check no mailing sent if no contacts.
   */
  public function testNoop() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    // Sanity check.
    $this->assertStats([]);

    // Step the whole journey.
    $result = civicrm_api3('Chasse', 'step', []);
    $this->assertEquals(0, $result['is_error']);
    // The stats should be the same.
    $this->assertStats([]);
    // We should NOT have any contacts in the mailing group.
    $result = civicrm_api3('GroupContact', 'get', [
        'group_id' => $this->mailing_group,
        'status' => 'Added',
      ]);
    $this->assertEquals(0, $result['count']);

    // There should not be any mailings.
    $result = civicrm_api3('Mailing', 'get', []);
    $this->assertEquals(0, $result['count']);

  }

  /**
   * Fixture for config.
   */
  public function configureChasse() {
    $config = [
      [
        'name' => 'Test Journey 1',
        'mailing_group' => $this->mailing_group,
        'mail_from' => $this->from_id,
        'steps' => [
          [ 'code' => 'S1', 'next_code' => 'S2', 'send_mailing' => $this->msg_tpl_1, 'add_to_group' => ''],
          [ 'code' => 'S2', 'next_code' => '', 'send_mailing' => $this->msg_tpl_2, 'add_to_group' => "1"],
        ]
      ],
    ];
    $this->journeys = Civi::settings()->set('chasse_config', $config);
  }

}
