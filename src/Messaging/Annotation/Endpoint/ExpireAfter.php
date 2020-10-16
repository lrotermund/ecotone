<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Annotation\Endpoint;

/**
 * @Annotation
 */
class ExpireAfter
{
    private int $time;

    public function __construct(array $values)
    {
        $this->time = $values['value'];
    }

    public function getTime(): int
    {
        return $this->time;
    }
}