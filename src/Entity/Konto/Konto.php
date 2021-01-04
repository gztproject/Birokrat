<?php

namespace App\Entity\Konto;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AggregateBase;
use App\Entity\User\User;

/**
 *
 * @ORM\Entity(repositoryClass="App\Repository\KontoRepository")
 */
class Konto extends KontoBase {
	/**
	 *
	 * @ORM\ManyToOne(targetEntity="App\Entity\Konto\KontoCategory", inversedBy="kontos")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $category;

	/**
	 *
	 * @ORM\Column(type="boolean")
	 */
	private $isActive;

	/**
	 *
	 *  @ORM\Column(type="smallint")
	 */
	private $type;

	/**
	 *
	 * @param CreateKontoCommand $c
	 * @param KontoCategory $category
	 * @param User $user
	 */
	public function __construct(CreateKontoCommand $c, KontoCategory $category, User $user) {
		parent::__construct ( $user );
		$this->name = $c->name;
		$this->number = $c->number;
		$this->description = $c->description;
		$this->isActive = $c->isActive;
		$this->category = $category;
		$this->type = $c->type;
	}

	/**
	 *
	 * @param UpdateKontoCommand $c
	 * @param User $user
	 * @return Konto
	 * @throws \Exception
	 */
	public function update(UpdateKontoCommand $c, User $user): Konto {
		if (! $this->isActive)
			throw new \Exception ( "Can't update an inactive konto." );
		parent::updateBase ( $user );
		$this->name = $c->name;
		$this->number = $c->number;
		$this->description = $c->description;
		return $this;
	}

	/**
	 *
	 * @param User $user
	 * @throws \Exception
	 */
	public function activate(User $user) {
		if ($this->isActive)
			throw new \Exception ( "Can't activate a konto that's already active." );
		parent::updateBase ( $user );
		$this->isActive = true;
	}

	/**
	 *
	 * @param User $user
	 * @throws \Exception
	 */
	public function deactivate(User $user) {
		if (! $this->isActive)
			throw new \Exception ( "Can't deactivate a konto that's already deactivated." );
		parent::updateBase ( $user );
		$this->isActive = false;
	}

	/**
	 *
	 * @param KontoCategory $category
	 * @param User $user
	 * @throws \Exception
	 * @return Konto
	 */
	public function removeCategory(KontoCategory $category, User $user): Konto {
		if ($this->category != $category)
			throw new \Exception ( "Can't remove category other than itself." );
		parent::updateBase ( $user );
		$this->category = null;
		return $this;
	}
	
	/**
	 * Returns Konto Category
	 * @return KontoCategory|NULL
	 */
	public function getCategory(): ?KontoCategory {
		return $this->category;
	}
	
	/**
	 * Returns Konto Class
	 * @return KontoClass|NULL
	 */
	public function getClass(): ?KontoClass {
		return $this->category->getClass ();
	}
	
	/**
	 * Returns the Konto state
	 * @return bool|NULL
	 */
	public function getIsActive(): ?bool {
		return $this->isActive;
	}
	
	/**
	 * Returns the full Konto number
	 * @return string
	 */
	public function getFullNumber(): string {
		$category = $this->getCategory ();
		$class = $category->getClass ();

		return ( string ) $class->getNumber () . substr ( ( string ) $category->getNumber (), - 1 ) . substr ( ( string ) $this->getNumber (), - 1 );
	}
	
	/**
	 * Returns the {number}-{name} string
	 * {@inheritDoc}
	 * @see \App\Entity\Konto\KontoBase::getNumberAndName()
	 */
	public function getNumberAndName(): string {
		return ( string ) $this->getFullNumber () . " - " . $this->name;
	}
	
	/**
	 * Returns the Konto type (active or passive). Use KontoTypes enum.
	 * @return int
	 */
	public function getType(): int {
		return $this->type;
	}	
}

/**
 * This is a konto type enum, telling us wether it's an active or passive konto
 * 0-active (debit-credit), 1-passive (credit-debit).
 *
 * @author gapi
 */
abstract class KontoTypes {
	/**
	 * Active konto (debit-credit)
	 */
	const active = 0;
	/**
	 * Passive konto (credit-debit)
	 */
	const passive = 1;
}
