<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\Tests\Mock;

final class StubQuery
{
    public $id;

    public function __construct($id = 5)
    {
        $this->id = $id;
    }
}
