<?php

namespace Ecotone\Messaging\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;
use Ecotone\Messaging\Support\Assert;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ClassReference
{
    private string $referenceName;

    public function __construct(string $referenceName)
    {
        Assert::notNullAndEmpty($referenceName, "Reference name can not be empty string");
        $this->referenceName = $referenceName;
    }

    public function getReferenceName(): string
    {
        return $this->referenceName;
    }
}