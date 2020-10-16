<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config\Annotation\ModuleConfiguration;

use Ecotone\AnnotationFinder\AnnotatedFinding;
use Ecotone\AnnotationFinder\AnnotationFinder;
use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Messaging\Config\Annotation\AnnotationModule;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistration;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistrationService;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\MessageHandlerBuilder;
use Ecotone\Messaging\Handler\MessageHandlerBuilderWithParameterConverters;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Modelling\Annotation\IgnorePayload;

/**
 * Class BaseAnnotationConfiguration
 * @package Ecotone\Messaging\Config\Annotation
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 * @internal
 */
abstract class MessageHandlerRegisterConfiguration extends NoExternalConfigurationModule implements AnnotationModule
{
    /**
     * @var array|MessageHandlerBuilder[]
     */
    private $messageHandlerBuilders;

    /**
     * AnnotationGatewayConfiguration constructor.
     *
     * @param MessageHandlerBuilder[] $messageHandlerBuilders
     */
    private function __construct(array $messageHandlerBuilders)
    {
        $this->messageHandlerBuilders = $messageHandlerBuilders;
    }

    /**
     * @inheritDoc
     */
    public static function create(AnnotationFinder $annotationRegistrationService): AnnotationModule
    {
        $messageHandlerBuilders    = [];
        $parameterConverterFactory = ParameterConverterAnnotationFactory::create();
        foreach ($annotationRegistrationService->findAnnotatedMethods(static::getMessageHandlerAnnotation()) as $annotationRegistration) {
            $annotation               = $annotationRegistration->getAnnotationForMethod();
            $relatedInterface = InterfaceToCall::create($annotationRegistration->getClassName(), $annotationRegistration->getMethodName());
            $messageHandlerBuilders[] = static::createMessageHandlerFrom($annotationRegistration)
                ->withMethodParameterConverters(
                    $parameterConverterFactory->createParameterConvertersWithReferences(InterfaceToCall::create($annotationRegistration->getClassName(), $annotationRegistration->getMethodName()), $annotation->parameterConverters, (bool)$relatedInterface->hasMethodAnnotation(TypeDescriptor::create(IgnorePayload::class)))
                );
        }

        return new static($messageHandlerBuilders);
    }

    /**
     * @return string
     */
    public static abstract function getMessageHandlerAnnotation(): string;

    public static abstract function createMessageHandlerFrom(AnnotatedFinding $annotationRegistration): MessageHandlerBuilderWithParameterConverters;

    /**
     * @inheritDoc
     */
    public function prepare(Configuration $configuration, array $extensionObjects, ModuleReferenceSearchService $moduleReferenceSearchService): void
    {
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            $configuration->registerMessageHandler($messageHandlerBuilder);
        }
    }

    /**
     * @inheritDoc
     */
    public function canHandle($extensionObject): bool
    {
        return false;
    }
}