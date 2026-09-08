<?php

namespace App\Entity\Organization;

class CreatePartnerEmailCommand
{
    public $id;

    public $email;

    public $name;

    public $role;

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}
