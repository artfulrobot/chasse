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
  public function testGetstats() {
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
    $this->assertStats([ 'S1' => 1, 'S2' => 2 ]);
  }

}
