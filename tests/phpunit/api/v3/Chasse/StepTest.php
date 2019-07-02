<?php

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
  public function testStepT2() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    // Set a step for a couple of contacts.
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[0]['id'],
      $chasse_processor->step_api_field => 'T1',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[1]['id'],
      $chasse_processor->step_api_field => 'T2',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[2]['id'],
      $chasse_processor->step_api_field => 'T2',
    ]);

    // Sanity check.
    $this->assertStats(['T1' => ['ready' => 1, 'all' => 1], 'T2' => ['ready' => 2, 'all' => 2]]);

    $result = civicrm_api3('Chasse', 'step', ['journey_id' => 'journey1', 'step' => 'T2']);
    $this->assertEquals(0, $result['is_error']);
    // The stats should now show no one in T2 but T1 should be the same.
    $this->assertStats(['T1' => ['ready' => 1, 'all' => 1]]);
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
  public function testJourney1() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    // Set a step for a couple of contacts.
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[0]['id'],
      $chasse_processor->step_api_field => 'T1',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[1]['id'],
      $chasse_processor->step_api_field => 'T2',
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[2]['id'],
      $chasse_processor->step_api_field => 'T2',
    ]);

    // Sanity check.
    $this->assertStats(['T1' => ['ready' => 1, 'all' => 1], 'T2' => ['ready' => 2, 'all' => 2]]);

    // Step the whole journey.
    $result = civicrm_api3('Chasse', 'step', ['journey_id' => 'journey1']);
    $this->assertEquals(0, $result['is_error']);
    // The stats should now show no one in T1 but one person in T2.
    $this->assertStats(['T2' => ['ready' => 1, 'all' => 1]]);
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
   * Check performing a journey that uses personal timelines.
   */
  public function testPersonalTimelines1() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    $yesterday = date('Y-m-d H:i:s', strtotime('yesterday'));
    $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
    // Set a step for a couple of contacts.
    civicrm_api3('Contact', 'create', [
      'id'                                    => $this->contact_fixtures[0]['id'],
      $chasse_processor->step_api_field       => 'S1',
      $chasse_processor->not_before_api_field => $yesterday, // should be ready
    ]);
    civicrm_api3('Contact', 'create', [
      'id'                                    => $this->contact_fixtures[1]['id'],
      $chasse_processor->step_api_field       => 'S2',
      $chasse_processor->not_before_api_field => $yesterday, // should be ready
    ]);
    civicrm_api3('Contact', 'create', [
      'id'                                    => $this->contact_fixtures[2]['id'],
      $chasse_processor->step_api_field       => 'S2',
      $chasse_processor->not_before_api_field => $tomorrow, // should NOT be ready
    ]);
    for ($i=0; $i<=2; $i++) {
      Civi::log()->info("chassetest: Contact #$i is ID " . $this->contact_fixtures[$i]['id']);
    }

    // Sanity check: we expect one contact ready in each step, and 2 contacts in all in step 2.
    $this->assertStats(['S1' => ['ready' => 1, 'all' => 1], 'S2' => ['ready' => 1, 'all' => 2]]);

    // Step the whole journey.
    $result = civicrm_api3('Chasse', 'step', ['journey_id' => 'journey2']);
    $this->assertEquals(0, $result['is_error']);
    // What should have happend:
    //             Originally       Expected
    // Contact 0   S1, ready        S2, not ready
    // Contact 1   S2, ready        (journey ended)
    // Contact 2   S2, not ready    S2, not ready (no change)
    $this->assertStats(['S2' => ['ready' => 0, 'all' => 2]]);

    $this->assertNotInGroup($this->contact_fixtures[0]['id'], $this->mailing_group, "Contact #0 should NOT be in mailing group.");
    $this->assertInGroup($this->contact_fixtures[1]['id'], $this->mailing_group, "Contact #1 should be in mailing group.");
    $this->assertNotInGroup($this->contact_fixtures[2]['id'], $this->mailing_group, "Contact #2 should NOT be in mailing group.");

    // The contacts in that mailing should be....

    // We should have a mailing ready for 1.
    $result = civicrm_api3('Mailing', 'get', [
      'msg_template_id' => $this->msg_tpl_1,
      'body_html'       => ['LIKE' => '%chasse_mailing_1%'],
      'scheduled_date'  => ['IS NOT NULL' => 1],
      'approved_date'   => ['IS NOT NULL' => 1],
    ]);
    $this->assertEquals(1, $result['count']);

    // The mailing should have 1 recipients.
    $result = civicrm_api3('MailingRecipients', 'get', [ 'mailing_id' => $result['id'] ]);
    $this->assertEquals(1, $result['count']);

  }

  /**
   * Check intervals added as expected.
   *
   * - if the not_before value was NULL, it should use NOW.
   * - if the not_before value was a date, it should go on that.
   */
  public function testPersonalTimelinesIntervalUpdates() {

    $this->configureChasse();

    $chasse_processor = new CRM_Chasse_Processor();

    $yesterday = date('Y-m-d H:i:s', strtotime('yesterday'));
    // Set a step for a couple of contacts.
    civicrm_api3('Contact', 'create', [
      'id'                                    => $this->contact_fixtures[0]['id'],
      $chasse_processor->step_api_field       => 'S1',
      $chasse_processor->not_before_api_field => $yesterday, // should be ready
    ]);
    civicrm_api3('Contact', 'create', [
      'id'                                    => $this->contact_fixtures[1]['id'],
      $chasse_processor->step_api_field       => 'S1',
      $chasse_processor->not_before_api_field => NULL,
    ]);
    for ($i=0; $i<=1; $i++) {
      Civi::log()->info("chassetest: Contact #$i is ID " . $this->contact_fixtures[$i]['id']);
    }

    // Sanity check: we expect one contact ready in each step, and 2 contacts in all in step 2.
    $this->assertStats(['S1' => ['ready' => 2, 'all' => 2]]);

    // Step the whole journey.
    $result = civicrm_api3('Chasse', 'step', ['journey_id' => 'journey2']);
    $this->assertEquals(0, $result['is_error']);
    // What should have happend:
    //             Originally       Expected
    // Contact 0   S1, ready        S2, not ready
    // Contact 1   S2, ready        S2, not ready
    $this->assertStats(['S2' => ['ready' => 0, 'all' => 2]]);

    // Check that the not_before date is correctly set for both of them.
    // Contact 0
    $result = civicrm_api3('Contact', 'getvalue', ['id' => $this->contact_fixtures[0]['id'], 'return' => $chasse_processor->not_before_api_field]);
    // Expect it to be 2 days from yesterday, i.e. tomorrow.
    $date = date('Y-m-d', strtotime($result));
    $this->assertEquals(date('Y-m-d', strtotime('tomorrow')), $date, "Expected contact 0's new not_before to be tomorrow.");
    // Contact 1:
    $result = civicrm_api3('Contact', 'getvalue', ['id' => $this->contact_fixtures[1]['id'], 'return' => $chasse_processor->not_before_api_field]);
    $date = date('Y-m-d', strtotime($result));
    $this->assertEquals(date('Y-m-d', strtotime('today + 2 days')), $date, "Expected contact 0's new not_before to be day after tomorrow.");
  }

  /**
   * Fixture for config.
   */
  public function configureChasse() {
    $config = [
      'next_id' => 3,
      'journeys' => [
        'journey1' => [
          'name'          => 'Test Journey 1',
          'id'            => 'journey1',
          'mailing_group' => $this->mailing_group,
          'steps'         => [
            [ 'code' => 'T1', 'next_code' => 'T2', 'send_mailing' => $this->msg_tpl_1, 'add_to_group' => '', 'mail_from' => $this->from_id ],
            [ 'code' => 'T2', 'next_code' => '', 'send_mailing' => $this->msg_tpl_2, 'add_to_group' => "1", 'mail_from' => $this->from_id ],
          ],
        ],
        'journey2' => [
          'name'          => 'Test 2',
          'id'            => 'journey2',
          'mailing_group' => $this->mailing_group,
          'steps'         => [
            [ 'code' => 'S1', 'next_code' => 'S2', 'send_mailing' => $this->msg_tpl_1, 'add_to_group' => '', 'interval' => '2 DAY', 'mail_from' => $this->from_id ],
            [ 'code' => 'S2', 'next_code' => '',   'send_mailing' => $this->msg_tpl_2, 'add_to_group' => "1", 'mail_from' => $this->from_id ],
          ],
        ],
      ]
    ];
    $this->journeys = Civi::settings()->set('chasse_config', $config);
  }

}
