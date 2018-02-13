<?php

namespace SimplyCodedSoftware\Messaging\Handler\Gateway;

use SimplyCodedSoftware\Messaging\Handler\Gateway\ParameterToMessageConverter\OnlyPayloadConverter;
use SimplyCodedSoftware\Messaging\Handler\InterfaceToCall;
use SimplyCodedSoftware\Messaging\Support\Assert;
use SimplyCodedSoftware\Messaging\Support\InvalidArgumentException;
use SimplyCodedSoftware\Messaging\Support\MessageBuilder;

/**
 * Class MethodCallToMessageConverter
 * @package SimplyCodedSoftware\Messaging\Handler\Gateway\Gateway
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class MethodCallToMessageConverter
{
    /**
     * @var InterfaceToCall
     */
    private $interfaceToCall;
    /**
     * @var array|ParameterToMessageConverter[]
     */
    private $methodArgumentConverters;

    /**
     * MethodCallToMessageConverter constructor.
     * @param string $interfaceToCall
     * @param string $methodName
     * @param array|ParameterToMessageConverter[] $methodArgumentConverters
     */
    public function __construct(string $interfaceToCall, string $methodName, array $methodArgumentConverters)
    {
        $this->initialize($interfaceToCall, $methodName, $methodArgumentConverters);
    }

    /**
     * @param array|MethodArgument[] $methodArguments
     * @return MessageBuilder
     */
    public function convertFor(array $methodArguments) : MessageBuilder
    {
        Assert::allInstanceOfType($methodArguments, MethodArgument::class);
        $messageBuilder = MessageBuilder::withPayload("empty");

        foreach ($methodArguments as $methodArgument) {
            foreach ($this->methodArgumentConverters as $methodParameterConverter) {
                if ($methodParameterConverter->isSupporting($methodArgument)) {
                    $messageBuilder = $methodParameterConverter->convertToMessage($methodArgument, $messageBuilder);
                }
            }
        }

        return $messageBuilder;
    }

    /**
     * @param string $interfaceToCall
     * @param string $methodName
     * @param array|ParameterToMessageConverter[] $methodArgumentConverters
     * @throws \SimplyCodedSoftware\Messaging\MessagingException
     */
    private function initialize(string $interfaceToCall, string $methodName, array $methodArgumentConverters) : void
    {
        Assert::allInstanceOfType($methodArgumentConverters, ParameterToMessageConverter::class);

        $this->interfaceToCall = InterfaceToCall::create($interfaceToCall, $methodName);

        if (empty($methodArgumentConverters) && $this->interfaceToCall->hasMoreThanOneParameter()) {
            throw InvalidArgumentException::create("You need to pass method argument converts for {$this->interfaceToCall}");
        }

        if (empty($methodArgumentConverters) && $this->interfaceToCall->hasSingleArguments()) {
            $methodArgumentConverters = [new OnlyPayloadConverter()];
        }

        $this->methodArgumentConverters = $methodArgumentConverters;
    }
}