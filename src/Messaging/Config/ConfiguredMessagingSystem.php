<?php

namespace Ecotone\Messaging\Config;

use Ecotone\Messaging\Endpoint\ExecutionPollingMetadata;
use Ecotone\Messaging\MessageChannel;

/**
 * Interface ConfiguredMessagingSystem
 * @package Ecotone\Messaging\Config
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface ConfiguredMessagingSystem
{
    /**
     * @param string $gatewayReferenceName
     * @throws \InvalidArgumentException if trying to find not existing gateway reference
     */
    public function getGatewayByName(string $gatewayReferenceName): object;

    /**
     * @param string $gatewayReferenceName
     * @throws \InvalidArgumentException if trying to find not existing gateway reference
     */
    public function getNonProxyGatewayByName(string $gatewayReferenceName): \Ecotone\Messaging\Config\NonProxyCombinedGateway;

    public function runConsoleCommand(string $commandName, array $parameters) : mixed;

    /**
     * @return GatewayReference[]
     */
    public function getGatewayList() : iterable;

    /**
     * @param string $channelName
     * @return MessageChannel
     * @throws ConfigurationException if trying to find not existing channel
     */
    public function getMessageChannelByName(string $channelName) : MessageChannel;

    public function run(string $endpointId, ?ExecutionPollingMetadata $executionPollingMetadata = null) : void;

    /**
     * @return string[]
     */
    public function list() : array;
}