<?php
declare(strict_types=1);

namespace Test\SimplyCodedSoftware\IntegrationMessaging\Scheduling;

use PHPUnit\Framework\TestCase;
use SimplyCodedSoftware\IntegrationMessaging\Scheduling\CronTrigger;
use SimplyCodedSoftware\IntegrationMessaging\Scheduling\SimpleTriggerContext;
use SimplyCodedSoftware\IntegrationMessaging\Scheduling\StubUTCClock;
use SimplyCodedSoftware\IntegrationMessaging\Support\InvalidArgumentException;

/**
 * Class CronTriggerTest
 * @package Test\SimplyCodedSoftware\IntegrationMessaging\Scheduling
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class CronTriggerTest extends TestCase
{
    public function test_trigger_at_next_minute()
    {
        $cronTrigger = CronTrigger::createWith("* * * * *");

        $this->assertEquals(
            StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 00:01:00"),
            $cronTrigger->nextExecutionTime(StubUTCClock::createWithCurrentTime("2017-01-01 00:00:01"), SimpleTriggerContext::createEmpty())
        );
    }

    public function test_trigger_at_current_minute()
    {
        $cronTrigger = CronTrigger::createWith("* * * * *");

        $this->assertEquals(
            StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 00:01:00"),
            $cronTrigger->nextExecutionTime(StubUTCClock::createWithCurrentTime("2017-01-01 00:01:00"), SimpleTriggerContext::createEmpty())
        );
    }

    public function test_trigger_at_every_5th_past_23rd_hour()
    {
        $cronTrigger = CronTrigger::createWith("*/5 */23 * * *");

        $this->assertEquals(
            StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:00:00"),
            $cronTrigger->nextExecutionTime(StubUTCClock::createWithCurrentTime("2017-01-01 01:01:00"), SimpleTriggerContext::createEmpty())
        );
    }

    public function test_throwing_exception_if_wrong_cron_expression_passed()
    {
        $this->expectException(InvalidArgumentException::class);

        CronTrigger::createWith("wrong");
    }

    public function test_passing_with_last_next_execution_time()
    {
        $cronTrigger = CronTrigger::createWith("*/5 */23 * * *");

        $this->assertEquals(
            StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:05:00"),
            $cronTrigger->nextExecutionTime(
                StubUTCClock::createWithCurrentTime("2017-01-01 23:01:00"),
                SimpleTriggerContext::createWith(
                    StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:05:00"),
                    StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:00:00")
                )
            )
        );
    }

    public function test_not_scheduling_next_if_last_was_not_finished()
    {
        $cronTrigger = CronTrigger::createWith("*/5 */23 * * *");

        $this->assertEquals(
            StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:00:00"),
            $cronTrigger->nextExecutionTime(
                StubUTCClock::createWithCurrentTime("2017-01-01 23:01:00"),
                SimpleTriggerContext::createWith(
                    StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:00:00"),
                    null
                )
            )
        );
    }

    public function test_not_scheduling_next_when_executed_is_not_last_scheduled()
    {
        $cronTrigger = CronTrigger::createWith("*/5 */23 * * *");

        $this->assertEquals(
            StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:05:00"),
            $cronTrigger->nextExecutionTime(
                StubUTCClock::createWithCurrentTime("2017-01-01 23:05:02"),
                SimpleTriggerContext::createWith(
                    StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:05:00"),
                    StubUTCClock::createEpochTimeFromDateTimeString("2017-01-01 23:00:00")
                )
            )
        );
    }
}