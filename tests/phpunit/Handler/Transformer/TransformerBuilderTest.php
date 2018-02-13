<?php

namespace Test\SimplyCodedSoftware\Messaging\Handler\Transformer;

use Fixture\Service\ServiceExpectingMessageAndReturningMessage;
use Fixture\Service\ServiceExpectingOneArgument;
use Fixture\Service\ServiceExpectingTwoArguments;
use Fixture\Service\ServiceWithoutReturnValue;
use SimplyCodedSoftware\Messaging\Channel\DirectChannel;
use SimplyCodedSoftware\Messaging\Channel\QueueChannel;
use SimplyCodedSoftware\Messaging\Config\InMemoryChannelResolver;
use SimplyCodedSoftware\Messaging\Handler\InMemoryReferenceSearchService;
use SimplyCodedSoftware\Messaging\Handler\Processor\MethodInvoker\MessageToHeaderParameterConverterBuilder;
use SimplyCodedSoftware\Messaging\Handler\Processor\MethodInvoker\MessageToPayloadParameterConverterBuilder;
use SimplyCodedSoftware\Messaging\Handler\Transformer\TransformerBuilder;
use SimplyCodedSoftware\Messaging\Support\InvalidArgumentException;
use SimplyCodedSoftware\Messaging\Support\MessageBuilder;
use Test\SimplyCodedSoftware\Messaging\MessagingTest;

/**
 * Class TransformerBuilder
 * @package SimplyCodedSoftware\Messaging\Handler\Transformer
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class TransformerBuilderTest extends MessagingTest
{
    public function test_passing_message_to_transforming_class_if_there_is_type_hint_for_it()
    {
        $payload = 'some';
        $outputChannel = QueueChannel::create();
        $outputChannelName = "output";
        $objectToInvoke = "objecToInvoke";
        $transformer = TransformerBuilder::create("someChannel", $outputChannelName, $objectToInvoke, 'send', 'test')
                            ->build(
                                InMemoryChannelResolver::createFromAssociativeArray([
                                    $outputChannelName => $outputChannel
                                ]),
                                InMemoryReferenceSearchService::createWith([
                                    $objectToInvoke => ServiceExpectingMessageAndReturningMessage::create($payload)
                                ])
                            );

        $transformer->handle(MessageBuilder::withPayload('some123')->build());

        $this->assertMessages(
            MessageBuilder::withPayload($payload)
                ->build(),
            $outputChannel->receive()
        );
    }

    public function test_passing_message_payload_as_default()
    {
        $payload = 'someBigPayload';
        $outputChannel = QueueChannel::create();
        $outputChannelName = 'output';
        $objectToInvokeReference = "service-a";
        $transformer = TransformerBuilder::create("inputChannel", $outputChannelName, $objectToInvokeReference, 'withReturnValue', 'test')
                            ->build(
                                InMemoryChannelResolver::createFromAssociativeArray([
                                    $outputChannelName => $outputChannel
                                ]),
                                InMemoryReferenceSearchService::createWith([
                                    $objectToInvokeReference => ServiceExpectingOneArgument::create()
                                ])
                            );

        $transformer->handle(MessageBuilder::withPayload($payload)->build());

        $this->assertMessages(
            MessageBuilder::withPayload($payload)
                ->build(),
            $outputChannel->receive()
        );
    }

    public function test_throwing_exception_if_void_method_provided_for_transformation()
    {
        $this->expectException(InvalidArgumentException::class);

        $outputChannelName = "outputChannelName";
        $objectToInvokeReference = "service-a";
        TransformerBuilder::create("inputChannel", $outputChannelName, $objectToInvokeReference, 'setName', 'test')
                            ->build(
                                InMemoryChannelResolver::createFromAssociativeArray([
                                    $outputChannelName => QueueChannel::create()
                                ]),
                                InMemoryReferenceSearchService::createWith([
                                    $objectToInvokeReference => ServiceWithoutReturnValue::create()
                                ])
                            );
    }

    public function test_not_sending_message_to_output_channel_if_transforming_method_returns_null()
    {
        $outputChannel = QueueChannel::create();
        $outputChannelName = "output";
        $objectToInvokeReference = "service-a";
        $transformer = TransformerBuilder::create(DirectChannel::create(), $outputChannelName, $objectToInvokeReference, 'withNullReturnValue', 'test')
                        ->build(
                            InMemoryChannelResolver::createFromAssociativeArray([
                                $outputChannelName => $outputChannel
                            ]),
                            InMemoryReferenceSearchService::createWith([
                                $objectToInvokeReference => ServiceExpectingOneArgument::create()
                            ])
                        );

        $transformer->handle(MessageBuilder::withPayload('some')->build());

        $this->assertNull($outputChannel->receive());
    }

    public function test_transforming_headers_if_array_returned_by_transforming_method()
    {
        $payload = 'someBigPayload';
        $outputChannel = QueueChannel::create();
        $inputChannelName = "input";
        $outputChannelName = "output";
        $objectToInvokeReference = "service-a";
        $transformer = TransformerBuilder::create($inputChannelName, $outputChannelName, $objectToInvokeReference, 'withArrayReturnValue', 'test')
                            ->build(
                                InMemoryChannelResolver::createFromAssociativeArray([
                                    $inputChannelName => DirectChannel::create(),
                                    $outputChannelName => $outputChannel
                                ]),
                                InMemoryReferenceSearchService::createWith([
                                    $objectToInvokeReference => ServiceExpectingOneArgument::create()
                                ])
                            );

        $transformer->handle(MessageBuilder::withPayload($payload)->build());

        $this->assertMessages(
            MessageBuilder::withPayload($payload)
                ->setHeader('0', $payload)
                ->build(),
            $outputChannel->receive()
        );
    }

    public function test_transforming_payload_if_array_returned_and_message_payload_is_also_array()
    {
        $payload = ["some payload"];
        $outputChannel = QueueChannel::create();
        $inputChannelName = "input";
        $outputChannelName = "output";
        $objectToInvokeReference = "service-a";
        $transformer = TransformerBuilder::create($inputChannelName, $outputChannelName, $objectToInvokeReference, 'withArrayTypeHintAndArrayReturnValue', 'test')
                        ->build(
                            InMemoryChannelResolver::createFromAssociativeArray([
                                $inputChannelName => DirectChannel::create(),
                                $outputChannelName => $outputChannel
                            ]),
                            InMemoryReferenceSearchService::createWith([
                                $objectToInvokeReference => ServiceExpectingOneArgument::create()
                            ])
                        );

        $transformer->handle(MessageBuilder::withPayload($payload)->build());

        $this->assertMessages(
            MessageBuilder::withPayload($payload)
                ->build(),
            $outputChannel->receive()
        );
    }

    public function test_transforming_with_custom_method_arguments_converters()
    {
        $payload = 'someBigPayload';
        $headerValue = 'abc';
        $outputChannel = QueueChannel::create();
        $outputChannelName = 'output';
        $inputChannelName = 'input';
        $objectToInvokeReference = "service-a";
        $transformerBuilder = TransformerBuilder::create($inputChannelName, $outputChannelName, $objectToInvokeReference, 'withReturnValue', 'test');
        $transformerBuilder->withMethodParameterConverters([
            MessageToPayloadParameterConverterBuilder::create('name'),
            MessageToHeaderParameterConverterBuilder::create('surname', 'token')
        ]);
        $transformer = $transformerBuilder
            ->build(
                InMemoryChannelResolver::createFromAssociativeArray([
                    $inputChannelName => DirectChannel::create(),
                    $outputChannelName => $outputChannel
                ]),
                InMemoryReferenceSearchService::createWith([
                    $objectToInvokeReference => ServiceExpectingTwoArguments::create()
                ])
            );

        $transformer->handle(
            MessageBuilder::withPayload($payload)
                ->setHeader('token', $headerValue)
                ->build()
        );

        $this->assertMessages(
            MessageBuilder::withPayload($payload . $headerValue)
                ->setHeader('token', $headerValue)
                ->build(),
            $outputChannel->receive()
        );
    }

    public function test_transforming_with_header_enricher()
    {
        $payload = 'someBigPayload';
        $headerValue = 'abc';
        $outputChannel = QueueChannel::create();
        $inputChannelName = "input";
        $outputChannelName = "output";
        $transformer = TransformerBuilder::createHeaderEnricher('test', $inputChannelName, $outputChannelName, [
                "token" => $headerValue,
                "correlation-id" => 1
            ])
            ->build(
                InMemoryChannelResolver::createFromAssociativeArray([
                    $inputChannelName => DirectChannel::create(),
                    $outputChannelName => $outputChannel
                ]),
                InMemoryReferenceSearchService::createEmpty()
            );

        $transformer->handle(
            MessageBuilder::withPayload($payload)
                ->build()
        );

        $this->assertMessages(
            MessageBuilder::withPayload($payload)
                ->setHeader('token', $headerValue)
                ->setHeader('correlation-id', 1)
                ->build(),
            $outputChannel->receive()
        );
    }
}