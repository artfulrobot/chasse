<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Chasse.Processjourneyschedules API Test Case
 * This is a generic test class implemented with PHPUnit.
 * @group headless
 */
class api_v3_Chasse_ProcessjourneyschedulesTest extends api_v3_Chasse_Base
{
  use \Civi\Test\Api3TestTrait;

  /**
   * Test that the scheduling logic works.
   */
  public function testScheduleMatching() {

    $cases = [
      [NULL, '2019-01-01 11:22:33', []], // Nothing scheduled
      [[], '2019-01-01 11:22:33', ['journey2']], // Scheduled for always.
      [['day_of_month' => 1], '2019-01-01 11:22:33', ['journey2']],
      [['day_of_month' => 2], '2019-01-01 11:22:33', []],
      [['days' => [1, 5, 7]], '2019-01-01 11:22:33', []], // 1 Jan 2019 = Tues, this should not be true.
      [['days' => [2, 5, 7]], '2019-01-01 11:22:33', ['journey2']], // should match.
      [['time_earliest' => '09:00'], '2019-01-01 11:22:33', ['journey2']], // should match.
      [['time_earliest' => '12:00'], '2019-01-01 11:22:33', []],
      [['time_latest' => '23:00'], '2019-01-01 11:22:33', ['journey2']], // should match.
      [['time_latest' => '10:00'], '2019-01-01 11:22:33', []],
      [['days' => [2], 'day_of_month' => 1, 'time_earliest' => '09:00', 'time_latest' => '23:00'], '2019-01-01 11:22:33', ['journey2']],
    ];
    foreach ($cases as $case) {
      list($schedule, $time, $expected) = $case;
      $config = [
        'next_id' => 2,
        'journeys' => [
          'journey1' => [
            'name'          => 'Test Journey 1',
            'id'            => 'journey1',
            'mailing_group' => $this->mailing_group,
            'mail_from'     => $this->from_id,
            'steps'         => [],
          ],
          'journey2' => [
            'name'          => 'Test 2',
            'id'            => 'journey2',
            'mailing_group' => $this->mailing_group,
            'mail_from'     => $this->from_id,
            'steps'         => [],
          ],
        ],
      ];
      if (is_array($schedule)) {
        $config['journeys']['journey2']['schedule'] = $schedule;
      }
      Civi::settings()->set('chasse_config', $config);

      $chasse_processor = new CRM_Chasse_Processor();
      $result = $chasse_processor->getScheduledJourneys(strtotime($time));
      $this->assertEquals($expected, $result);
    }
  }

  /**
   * Test the lock works.
   *
   */
  public function testLockWorks() {
    $chasse_processor = new CRM_Chasse_Processor();
    $this->assertTrue($chasse_processor->attemptToLock(), "Failed to lock.");
    $this->assertTrue($chasse_processor->lockExists(), "Lock not detected.");
    $this->assertFalse($chasse_processor->attemptToLock(), "Obtained lock when lock exists.");
    $chasse_processor->releaseLock();
    $this->assertFalse($chasse_processor->lockExists(), "Lock not released.");
  }

}
