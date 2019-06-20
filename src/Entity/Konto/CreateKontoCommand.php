<?php

namespace App\Entity\Konto;

abstract class CreateKontoCommand extends CreateKontoBaseCommand
{
	public $isActive;
}
