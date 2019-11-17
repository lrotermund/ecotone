<?php


namespace Test\Ecotone\Messaging\Fixture\Annotation\Async;

use Ecotone\Messaging\Annotation\Async;
use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Modelling\Annotation\CommandHandler;
use Ecotone\Modelling\Annotation\EventHandler;

/**
 * Class AsyncEventHandlerExample
 * @package Test\Ecotone\Messaging\Fixture\Annotation\Async
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @MessageEndpoint()
 * @Async(channelName="asyncChannel")
 */
class AsyncCommandHandlerWithoutIdExample
{
    /**
     * @param \stdClass $event
     *
     * @CommandHandler()
     * @Async(channelName="asyncChannel")
     */
    public function doSomething(\stdClass $event) : void
    {

    }
}