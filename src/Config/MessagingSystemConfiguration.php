<?php

namespace SimplyCodedSoftware\IntegrationMessaging\Config;

use SimplyCodedSoftware\IntegrationMessaging\Channel\ChannelInterceptor;
use SimplyCodedSoftware\IntegrationMessaging\Channel\ChannelInterceptorBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Channel\MessageChannelBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Endpoint\ConsumerBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Handler\ChannelResolver;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Gateway\GatewayBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Handler\InMemoryReferenceSearchService;
use SimplyCodedSoftware\IntegrationMessaging\Handler\MessageHandlerBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Endpoint\ConsumerEndpointFactory;
use SimplyCodedSoftware\IntegrationMessaging\Endpoint\MessageHandlerConsumerBuilderFactory;
use SimplyCodedSoftware\IntegrationMessaging\Handler\ReferenceSearchService;
use SimplyCodedSoftware\IntegrationMessaging\PollableChannel;

/**
 * Class MessagingSystemConfiguration
 * @package SimplyCodedSoftware\IntegrationMessaging\Config
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
final class MessagingSystemConfiguration implements Configuration
{
    /**
     * @var MessageChannelBuilder[]
     */
    private $channelsBuilders = [];
    /**
     * @var ChannelInterceptorBuilder[]
     */
    private $channelInterceptorBuilders = [];
    /**
     * @var MessageHandlerBuilder[]
     */
    private $messageHandlerBuilders = [];
    /**
     * @var MessageHandlerConsumerBuilderFactory[]
     */
    private $consumerFactories = [];
    /**
     * @var Module[]
     */
    private $modules = [];
    /**
     * @var ModuleExtension[][]
     */
    private $moduleExtensions = [];
    /**
     * @var array|GatewayBuilder[]
     */
    private $gatewayBuilders = [];
    /**
     * @var ConfigurationObserver
     */
    private $configurationObserver;

    /**
     * Only one instance at time
     *
     * MessagingSystemConfiguration constructor.
     * @param ModuleRetrievingService $moduleConfigurationRetrievingService
     * @param ConfigurationObserver $configurationObserver
     */
    private function __construct(ModuleRetrievingService $moduleConfigurationRetrievingService, ConfigurationObserver $configurationObserver)
    {
        $this->initialize($moduleConfigurationRetrievingService);
        $this->configurationObserver = $configurationObserver;

        foreach ($this->modules as $module) {
            $module->prepare($this, $this->moduleExtensions[$module->getName()], $configurationObserver);
        }
    }

    /**
     * @param ModuleRetrievingService $moduleConfigurationRetrievingService
     * @return MessagingSystemConfiguration
     */
    public static function prepare(ModuleRetrievingService $moduleConfigurationRetrievingService) : self
    {
        return new self($moduleConfigurationRetrievingService, NullObserver::create());
    }

    /**
     * @param ModuleRetrievingService $moduleRetrievingService
     * @param ConfigurationObserver $configurationObserver
     * @return MessagingSystemConfiguration
     */
    public static function prepareWitObserver(ModuleRetrievingService $moduleRetrievingService, ConfigurationObserver $configurationObserver) : self
    {
        return new self($moduleRetrievingService, $configurationObserver);
    }

    /**
     * @param MessageChannelBuilder $messageChannelBuilder
     * @return MessagingSystemConfiguration
     */
    public function registerMessageChannel(MessageChannelBuilder $messageChannelBuilder): self
    {
        $this->channelsBuilders[] = $messageChannelBuilder;
        $this->configurationObserver->notifyMessageChannelWasRegistered($messageChannelBuilder->getMessageChannelName(), (string)$messageChannelBuilder);
        $this->requireReferences($messageChannelBuilder->getRequiredReferenceNames());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerChannelInterceptor(ChannelInterceptorBuilder $channelInterceptorBuilder): MessagingSystemConfiguration
    {
        $this->channelInterceptorBuilders[$channelInterceptorBuilder->getImportanceOrder()][] = $channelInterceptorBuilder;
        $this->requireReferences($channelInterceptorBuilder->getRequiredReferenceNames());

        return $this;
    }

    /**
     * @param MessageHandlerBuilder $messageHandlerBuilder
     * @return MessagingSystemConfiguration
     */
    public function registerMessageHandler(MessageHandlerBuilder $messageHandlerBuilder) : self
    {
        $this->requireReferences($messageHandlerBuilder->getRequiredReferenceNames());

        $this->messageHandlerBuilders[] = $messageHandlerBuilder;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerConsumer(ConsumerBuilder $consumerBuilder): MessagingSystemConfiguration
    {
        return $this;
    }

    /**
     * @param GatewayBuilder $gatewayBuilder
     * @return MessagingSystemConfiguration
     */
    public function registerGatewayBuilder(GatewayBuilder $gatewayBuilder) : self
    {
        $this->gatewayBuilders[] = $gatewayBuilder;
        $this->configurationObserver->notifyGatewayBuilderWasRegistered($gatewayBuilder->getReferenceName(), (string)$gatewayBuilder, $gatewayBuilder->getInterfaceName());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerConsumerFactory(MessageHandlerConsumerBuilderFactory $consumerFactory): MessagingSystemConfiguration
    {
        $this->consumerFactories[] = $consumerFactory;

        return $this;
    }

    /**
     * Initialize messaging system from current configuration
     *
     * @param ReferenceSearchService $externalReferenceSearchService
     * @param ConfigurationVariableRetrievingService $configurationVariableRetrievingService
     * @return ConfiguredMessagingSystem
     * @throws \SimplyCodedSoftware\IntegrationMessaging\Endpoint\NoConsumerFactoryForBuilderException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function buildMessagingSystemFromConfiguration(ReferenceSearchService $externalReferenceSearchService, ConfigurationVariableRetrievingService $configurationVariableRetrievingService) : ConfiguredMessagingSystem
    {
        foreach ($this->modules as $module) {
            $module->configure(
                $this,
                $this->moduleExtensions[$module->getName()],
                $configurationVariableRetrievingService,
                $externalReferenceSearchService
            );
        }

        $referenceSearchService = InMemoryReferenceSearchService::createWithReferenceService($externalReferenceSearchService, $this->modules);
        $channelResolver = $this->createChannelResolver($referenceSearchService);
        $gateways = [];
        foreach ($this->gatewayBuilders as $gatewayBuilder) {
            $gatewayReference = GatewayReference::createWith($gatewayBuilder, $referenceSearchService, $channelResolver);
            $gateways[]       = $gatewayReference;
            $this->configurationObserver->notifyGatewayWasBuilt($gatewayReference);
        }

        $consumerEndpointFactory = new ConsumerEndpointFactory($channelResolver, $referenceSearchService, $this->consumerFactories);
        $consumers = [];

        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            $consumers[] = $consumerEndpointFactory->createForMessageHandler($messageHandlerBuilder);
        }

        $messagingSystem = MessagingSystem::create($consumers, $gateways, $channelResolver);
        $this->configurationObserver->notifyConfigurationWasFinished($messagingSystem);
        foreach ($this->modules as $moduleMessagingConfiguration) {
            $moduleMessagingConfiguration->postConfigure($messagingSystem);
        }

        return $messagingSystem;
    }

    /**
     * @param ReferenceSearchService $referenceSearchService
     * @return ChannelResolver
     */
    private function createChannelResolver(ReferenceSearchService $referenceSearchService) : ChannelResolver
    {
        $channelInterceptorsByImportance = $this->channelInterceptorBuilders;
        arsort($channelInterceptorsByImportance);
        $channelInterceptorsByChannelName = [];

        foreach ($channelInterceptorsByImportance as $channelInterceptors) {
            foreach ($channelInterceptors as $channelInterceptor) {
                $channelInterceptorsByChannelName[$channelInterceptor->relatedChannelName()][] = $channelInterceptor->build($referenceSearchService);
            }
        }

        $channels = [];
        foreach ($this->channelsBuilders as $channelsBuilder) {
            $messageChannel = $channelsBuilder->build($referenceSearchService);
            if (array_key_exists($channelsBuilder->getMessageChannelName(), $channelInterceptorsByChannelName)) {
                $interceptors = $channelInterceptorsByChannelName[$channelsBuilder->getMessageChannelName()];
                if ($messageChannel instanceof PollableChannel) {
                    $messageChannel = new PollableChannelInterceptorAdapter($messageChannel, $interceptors);
                } else {
                    $messageChannel = new EventDrivenChannelInterceptorAdapter($messageChannel, $interceptors);
                }
            }

            $channels[] = NamedMessageChannel::create($channelsBuilder->getMessageChannelName(), $messageChannel);
        }

        return InMemoryChannelResolver::create($channels);
    }

    /**
     * @param ModuleRetrievingService $moduleConfigurationRetrievingService
     */
    private function initialize(ModuleRetrievingService $moduleConfigurationRetrievingService) : void
    {
        $modules = $moduleConfigurationRetrievingService->findAllModuleConfigurations();
        $moduleExtensions = $moduleConfigurationRetrievingService->findAllModuleExtensionConfigurations();
        foreach ($moduleExtensions as $moduleExtension) {
            $this->moduleExtensions[$moduleExtension->getName()][] = $moduleExtension;
        }

        foreach ($modules as $module) {
            if (!array_key_exists($module->getName(), $this->moduleExtensions)) {
                $this->moduleExtensions[$module->getName()] = [];
            }

            $this->modules[$module->getName()] = $module;
        }
    }

    /**
     * @param string[] $referenceNames
     */
    private function requireReferences(array $referenceNames) : void
    {
        foreach ($referenceNames as $requiredReferenceName) {
            if ($requiredReferenceName) {
                $this->configurationObserver->notifyRequiredAvailableReference($requiredReferenceName);
            }
        }
    }

    /**
     * Only one instance at time
     *
     * @internal
     */
    private function __clone()
    {

    }
}