<?php

namespace Test\Ecotone\Modelling\Fixture\InterceptingAggregate;

use Ecotone\Messaging\Annotation\Interceptor\Around;
use Ecotone\Messaging\Annotation\Interceptor\Before;
use Ecotone\Modelling\Annotation\CommandHandler;

class AddCurrentUserId
{
    private ?string $userId = null;

    #[CommandHandler("addCurrentUserId")]
    public function setCurrentUserId(string $userId) : void
    {
        $this->userId = $userId;
    }

    #[Before(pointcut: Basket::class)]
    public function addCurrentUserId(array $payload) : array
    {
        return array_merge($payload, ["userId" => $this->userId]);
    }

    #[Around(pointcut: Basket::class)]
    public function logAction(?Basket $basket) : void
    {
        $logged = true;
//        do some logging
    }
}