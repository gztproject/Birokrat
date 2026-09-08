<?php

namespace App\Entity\Transaction;

use App\Entity\Konto\Konto;

class CreateAllocationCommand
{
    public $konto;
    public $amount;
    public $note;

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}
