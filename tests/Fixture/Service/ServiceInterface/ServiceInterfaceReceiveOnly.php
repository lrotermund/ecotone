<?php

namespace Fixture\Service\ServiceInterface;

/**
 * Interface ServiceInterface
 * @package Fixture\Service
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface ServiceInterfaceReceiveOnly
{
    public function sendMail() : string;
}