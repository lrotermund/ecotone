<?php

namespace Ecotone\Modelling\Config;

use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Modelling\EventBus;

/**
 * Class EventPublisherRouter
 * @package Ecotone\Modelling\Config
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class EventBusRouter
{
    private array $channelMapping = [];

    /**
     * CommandBusRouter constructor.
     *
     * @param array           $channelMapping
     */
    public function __construct(array $channelMapping)
    {
        $this->channelMapping = $channelMapping;
    }

    public function routeByObject(object $object) : array
    {
        $resolvedChannels = [];
        $reflectionClass = new \ReflectionClass($object);
        $parent = $reflectionClass;
        if (array_key_exists(TypeDescriptor::OBJECT, $this->channelMapping)) {
            $resolvedChannels =  array_merge($resolvedChannels, $this->channelMapping[TypeDescriptor::OBJECT]);
        }
        while ($parent = $parent->getParentClass()) {
            $resolvedChannels = array_merge($resolvedChannels, $this->getChannelsForClassName($parent));
        }

        return array_values(array_unique(array_merge($resolvedChannels, $this->getChannelsForClassName($reflectionClass))));
    }

    /**
     * @param \ReflectionClass $class
     *
     * @return array
     * @throws \ReflectionException
     */
    private function getChannelsForClassName(\ReflectionClass $class) : array
    {
        $channelNames = [];
        foreach ($class->getInterfaceNames() as $interfaceName) {
            $channelNames = array_merge($channelNames, $this->getChannelsForClassName(new \ReflectionClass($interfaceName)));
        }

        $className = $class->getName();
        if (array_key_exists($className, $this->channelMapping)) {
            $channelNames =  array_merge($channelNames, $this->channelMapping[$className]);
        }

        return $channelNames;
    }

    /**
     * @param string|null $name
     *
     * @return array
     * @throws \Ecotone\Messaging\MessagingException
     */
    public function routeByName(?string $name) : array
    {
        if (is_null($name)) {
            throw ConfigurationException::create("Can't send via name using EventBus without " . EventBus::CHANNEL_NAME_BY_NAME . " header defined");
        }

        $resultChannels = [];
        foreach ($this->channelMapping as $listenFor => $destinationChannels) {
            $listenFor = str_replace("\\", "\\\\", $listenFor);
            if (preg_match("#^" . str_replace("*", ".*", $listenFor) . "$#", $name)) {
                $resultChannels = array_merge($resultChannels, $destinationChannels);
            }
        }

        return array_unique($resultChannels);
    }

    public static function isRegexBasedRoute(string $channelName) : bool
    {
        return preg_match("#\*#", $channelName);
    }
}