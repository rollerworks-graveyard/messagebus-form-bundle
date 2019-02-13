<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\Tests\Mock;

final class StubCommand
{
    public $id;
    public $username;
    public $profile;

    public function __construct($id = 5, $username = null, $profile = null)
    {
        $this->id       = $id;
        $this->username = $username;
        $this->profile  = $profile;
    }
}
