<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Chasse.Getstats API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_Chasse_GetstatsTest extends api_v3_Chasse_Base
{

  /**
   * Check our SQL works for counting contacts per step.
   */
  public function testGetstatsNormal() {
    $result = civicrm_api3('Chasse', 'Getstats', []);
    $this->assertEmpty($result['values']);

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

    // We're not using any not_before dates, so they should all be ready.
    $this->assertStats(['S1' => ['ready' => 1, 'all' => 1], 'S2' => ['ready' => 2, 'all' => 2]]);

    // change the not_before dates.
    $yesterday = date('Y-m-d H:i:s', strtotime('yesterday'));
    $tomorrow = date('Y-m-d H:i:s', strtotime('tomorrow'));
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[0]['id'],
      $chasse_processor->not_before_api_field => $yesterday, // should be ready
    ]);
    civicrm_api3('Contact', 'create', [
      'id' => $this->contact_fixtures[2]['id'],
      $chasse_processor->not_before_api_field => $tomorrow, // should NOT be ready
    ]);

    // Sanity check: we expect one contact ready in each step, and 2 contacts in all in step 2.
    $this->assertStats(['S1' => ['ready' => 1, 'all' => 1], 'S2' => ['ready' => 1, 'all' => 2]]);
  }

  /**
   * Check our SQL works for excluding deleted contacts #6
   */
  public function testGetstatsExcludesDeleted() {
    $result = civicrm_api3('Chasse', 'Getstats', []);
    $this->assertEmpty($result['values']);

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
    // Delete contact 3 (index 2).
    civicrm_api3('Contact', 'delete', [
      'id' => $this->contact_fixtures[2]['id'],
    ]);
    $this->assertStats([ 'S1' => ['ready' => 1, 'all' => 1], 'S2' => ['ready' => 1, 'all' => 1] ]);
  }

}
