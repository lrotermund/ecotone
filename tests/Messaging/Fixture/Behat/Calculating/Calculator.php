<?php
declare(strict_types=1);


namespace Test\Ecotone\Messaging\Fixture\Behat\Calculating;

use Ecotone\Messaging\Annotation\MessageGateway;
use Ecotone\Messaging\Annotation\MessageEndpoint;

interface Calculator
{
    #[MessageGateway("calculateChannel")]
    #[BeforeMultiplyCalculation(2)]
    #[AroundSumCalculation(3)]
    #[AfterMultiplyCalculation(2)]
    public function calculate(int $amount) : int;
}