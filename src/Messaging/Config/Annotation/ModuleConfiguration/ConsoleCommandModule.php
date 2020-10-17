<?php
declare(strict_types=1);


namespace Ecotone\Messaging\Config\Annotation\ModuleConfiguration;


use Ecotone\AnnotationFinder\AnnotatedFinding;
use Ecotone\AnnotationFinder\AnnotatedMethod;
use Ecotone\AnnotationFinder\AnnotationFinder;
use Ecotone\Messaging\Annotation\AsynchronousRunningEndpoint;
use Ecotone\Messaging\Annotation\ModuleAnnotation;
use Ecotone\Messaging\Annotation\ConsoleCommand;
use Ecotone\Messaging\Annotation\Scheduled;
use Ecotone\Messaging\Config\Annotation\AnnotatedDefinitionReference;
use Ecotone\Messaging\Config\Annotation\AnnotationModule;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistration;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Config\ConsoleCommandConfiguration;
use Ecotone\Messaging\Config\OneTimeCommandParameter;
use Ecotone\Messaging\Config\OneTimeCommandResultSet;
use Ecotone\Messaging\Endpoint\ConsumerLifecycleBuilder;
use Ecotone\Messaging\Endpoint\InboundChannelAdapter\InboundChannelAdapterBuilder;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\MessageHandlerBuilderWithParameterConverters;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\HeaderBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\ReferenceBuilder;
use Ecotone\Messaging\Handler\ServiceActivator\ServiceActivatorBuilder;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * @ModuleAnnotation()
 */
class ConsoleCommandModule extends NoExternalConfigurationModule implements AnnotationModule
{
    const ECOTONE_COMMAND_PARAMETER_PREFIX = "ecotone.oneTimeCommand.";

    /**
     * @var ServiceActivatorBuilder[]
     */
    private array $oneTimeCommandHandlers;
    /**
     * @var ConsoleCommandConfiguration[]
     */
    private array $oneTimeCommandConfigurations;

    private function __construct(array $oneTimeCommands, array $oneTimeCommandConfigurations)
    {
        $this->oneTimeCommandHandlers = $oneTimeCommands;
        $this->oneTimeCommandConfigurations = $oneTimeCommandConfigurations;
    }

    public static function create(AnnotationFinder $annotationRegistrationService): \Ecotone\Messaging\Config\Annotation\AnnotationModule
    {
        $messageHandlerBuilders    = [];
        $oneTimeConfigurations = [];

        foreach ($annotationRegistrationService->findAnnotatedMethods(ConsoleCommand::class) as $annotationRegistration) {
            /** @var ConsoleCommand $annotation */
            $annotation               = $annotationRegistration->getAnnotationForMethod();
            $commandName                     = $annotation->name;
            $className    = $annotationRegistration->getClassName();
            $methodName               = $annotationRegistration->getMethodName();

            list($messageHandlerBuilder, $oneTimeCommandConfiguration) = self::prepareConsoleCommand($annotationRegistration, $className, $methodName, $commandName);

            $messageHandlerBuilders[] = $messageHandlerBuilder;
            $oneTimeConfigurations[]     = $oneTimeCommandConfiguration;
        }

        return new static($messageHandlerBuilders, $oneTimeConfigurations);
    }

    public static function prepareConsoleCommand(AnnotatedMethod $annotatedMethod, string $className, string $methodName, string $commandName): array
    {
        $parameterConverters = [];
        $parameters          = [];
        $classReflection     = new \ReflectionClass($className);

        $interfaceToCall = InterfaceToCall::create($className, $methodName);

        if ($interfaceToCall->canReturnValue() && !$interfaceToCall->getReturnType()->equals(TypeDescriptor::create(OneTimeCommandResultSet::class))) {
            throw InvalidArgumentException::create("One Time Command {$interfaceToCall} must have void or " . OneTimeCommandResultSet::class . " return type");
        }

        foreach ($interfaceToCall->getInterfaceParameters() as $interfaceParameter) {
            if ($interfaceParameter->getTypeDescriptor()->isClassOrInterface()) {
                $parameterConverters[] = ReferenceBuilder::create($interfaceParameter->getName(), $interfaceParameter->getTypeDescriptor()->toString());
            } else {
                $parameterConverters[] = HeaderBuilder::create($interfaceParameter->getName(), self::ECOTONE_COMMAND_PARAMETER_PREFIX . $interfaceParameter->getName());
                $parameters[]          = $interfaceParameter->hasDefaultValue()
                    ? OneTimeCommandParameter::createWithDefaultValue($interfaceParameter->getName(), $interfaceParameter->getDefaultValue())
                    : OneTimeCommandParameter::create($interfaceParameter->getName());
            }
        }

        $inputChannel                = "ecotone.channel." . $commandName;
        if ($classReflection->getConstructor() && $classReflection->getConstructor()->getParameters()) {
            $serviceActivatorBuilder     = ServiceActivatorBuilder::create(AnnotatedDefinitionReference::getReferenceFor($annotatedMethod), $methodName);
        }else {
            $serviceActivatorBuilder     = ServiceActivatorBuilder::createWithDirectReference(new $className(), $methodName);
        }

        $messageHandlerBuilder       = $serviceActivatorBuilder
            ->withEndpointId("ecotone.endpoint." . $commandName)
            ->withInputChannelName($inputChannel)
            ->withMethodParameterConverters($parameterConverters);
        $oneTimeCommandConfiguration = ConsoleCommandConfiguration::create($inputChannel, $commandName, $parameters);

        return array($messageHandlerBuilder, $oneTimeCommandConfiguration);
    }

    public function prepare(Configuration $configuration,array $extensionObjects,ModuleReferenceSearchService $moduleReferenceSearchService) : void
    {
        foreach ($this->oneTimeCommandHandlers as $oneTimeCommand) {
            $configuration->registerMessageHandler($oneTimeCommand);
        }
        foreach ($this->oneTimeCommandConfigurations as $oneTimeCommandConfiguration) {
            $configuration->registerConsoleCommand($oneTimeCommandConfiguration);
        }
    }

    public function canHandle($extensionObject) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return "oneTimeCommandModule";
    }
}