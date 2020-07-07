<?php

namespace Test\Ecotone\Modelling\Fixture\InterceptedEventAggregate\VerifyAccessToSavingLogs;

use Ecotone\Messaging\Annotation\Interceptor\Around;
use Ecotone\Messaging\Annotation\Interceptor\MethodInterceptor;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;
use Test\Ecotone\Modelling\Fixture\InterceptedEventAggregate\Logger;

/**
 * @MethodInterceptor()
 */
class HasEnoughPermissions
{
    /**
     * @Around(
     *     pointcut="@(Test\Ecotone\Modelling\Fixture\InterceptedEventAggregate\VerifyAccessToSavingLogs\ValidateExecutor)"
     * )
     */
    public function validate(MethodInvocation $methodInvocation, ?Logger $logger)
    {
        if (is_null($logger)) {
            return $methodInvocation->proceed();
        }

        $data = $methodInvocation->getArguments()[0];

        if (!$logger->hasAccess($data["executorId"])) {
            throw new \InvalidArgumentException("Not enough permissions");
        }

        return $methodInvocation->proceed();
    }
}