<?php

namespace SimplyCodedSoftware\IntegrationMessaging\Handler\Filter;

use Fixture\Handler\Selector\MessageSelectorExample;
use SimplyCodedSoftware\IntegrationMessaging\Channel\QueueChannel;
use SimplyCodedSoftware\IntegrationMessaging\Config\InMemoryChannelResolver;
use SimplyCodedSoftware\IntegrationMessaging\Handler\InMemoryReferenceSearchService;
use SimplyCodedSoftware\IntegrationMessaging\MessagingException;
use SimplyCodedSoftware\IntegrationMessaging\Support\InvalidArgumentException;
use SimplyCodedSoftware\IntegrationMessaging\Support\MessageBuilder;
use Test\SimplyCodedSoftware\IntegrationMessaging\MessagingTest;

/**
 * Class MessageFilterBuilderTest
 * @package SimplyCodedSoftware\IntegrationMessaging\Handler\Filter
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class MessageFilterBuilderTest extends MessagingTest
{
    /**
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws \Exception
     */
    public function test_forwarding_message_if_selector_returns_true()
    {
        $outputChannelName = "outputChannel";
        $outputChannel = QueueChannel::create();

        $messageFilter = MessageFilterBuilder::createWithReferenceName( MessageSelectorExample::class, "accept")
                            ->withOutputMessageChannel($outputChannelName)
                            ->build(
                                InMemoryChannelResolver::createFromAssociativeArray([
                                    $outputChannelName => $outputChannel
                                ]),
                                InMemoryReferenceSearchService::createWith([
                                    MessageSelectorExample::class => MessageSelectorExample::create()
                                ])
                            );

        $message = MessageBuilder::withPayload("some")->build();

        $messageFilter->handle($message);

        $this->assertEquals(
            $message,
            $outputChannel->receive()
        );
    }

    public function test_discard_message_if_selector_returns_false()
    {
        $outputChannelName = "outputChannel";
        $outputChannel = QueueChannel::create();

        $messageFilter = MessageFilterBuilder::createWithReferenceName(MessageSelectorExample::class, "refuse")
            ->withOutputMessageChannel($outputChannelName)
            ->build(
                InMemoryChannelResolver::createFromAssociativeArray([
                    $outputChannelName => $outputChannel
                ]),
                InMemoryReferenceSearchService::createWith([
                    MessageSelectorExample::class => MessageSelectorExample::create()
                ])
            );

        $message = MessageBuilder::withPayload("some")->build();

        $messageFilter->handle($message);

        $this->assertNull($outputChannel->receive());
    }

    /**
     * @throws MessagingException
     */
    public function test_throwing_exception_if_selector_return_type_is_different_than_boolean()
    {
        $outputChannelName = "outputChannel";
        $outputChannel = QueueChannel::create();

        $this->expectException(InvalidArgumentException::class);

        MessageFilterBuilder::createWithReferenceName(MessageSelectorExample::class, "wrongReturnType")
            ->withOutputMessageChannel($outputChannelName)
            ->build(
                InMemoryChannelResolver::createFromAssociativeArray([
                    $outputChannelName => $outputChannel
                ]),
                InMemoryReferenceSearchService::createWith([
                    MessageSelectorExample::class => MessageSelectorExample::create()
                ])
            );
    }

    /**
     * @throws MessagingException
     */
    public function test_publishing_message_to_discard_channel_if_defined()
    {
        $outputChannelName = "outputChannel";
        $outputChannel = QueueChannel::create();
        $discardChannelName = "discardChannel";
        $discardChannel = QueueChannel::create();

        $messageFilter = MessageFilterBuilder::createWithReferenceName(MessageSelectorExample::class, "refuse")
            ->withOutputMessageChannel($outputChannelName)
            ->withDiscardChannelName($discardChannelName)
            ->build(
                InMemoryChannelResolver::createFromAssociativeArray([
                    $outputChannelName => $outputChannel,
                    $discardChannelName => $discardChannel
                ]),
                InMemoryReferenceSearchService::createWith([
                    MessageSelectorExample::class => MessageSelectorExample::create()
                ])
            );

        $message = MessageBuilder::withPayload("some")->build();

        $messageFilter->handle($message);

        $this->assertNull($outputChannel->receive());
        $this->assertEquals($message, $discardChannel->receive());
    }

    /**
     * @throws MessagingException
     */
    public function test_throwing_exception_on_discard_if_defined()
    {
        $outputChannelName = "outputChannel";
        $outputChannel = QueueChannel::create();

        $messageFilter = MessageFilterBuilder::createWithReferenceName(MessageSelectorExample::class, "refuse")
            ->withOutputMessageChannel($outputChannelName)
            ->withThrowingExceptionOnDiscard(true)
            ->build(
                InMemoryChannelResolver::createFromAssociativeArray([
                    $outputChannelName => $outputChannel
                ]),
                InMemoryReferenceSearchService::createWith([
                    MessageSelectorExample::class => MessageSelectorExample::create()
                ])
            );

        $message = MessageBuilder::withPayload("some")->build();

        $this->expectException(MessagingException::class);

        $messageFilter->handle($message);
    }

    public function test_converting_to_string()
    {
        $inputChannelName = 'inputChannel';
        $endpointName = "someName";

        $this->assertEquals(
            MessageFilterBuilder::createWithReferenceName("ref-name", "method-name")
                ->withInputChannelName($inputChannelName)
                ->withEndpointId($endpointName),
            sprintf("Message filter - %s:%s with name `%s` for input channel `%s`", "ref-name", "method-name", $endpointName, $inputChannelName)
        );
    }
}