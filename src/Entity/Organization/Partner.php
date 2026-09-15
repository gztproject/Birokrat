<?php

namespace App\Entity\Organization;

use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\Invoice\Invoice;
use App\Mailer\RecipientListParser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Mime\Address;

#[ORM\Entity(repositoryClass: \App\Repository\Organization\PartnerRepository::class)]
class Partner extends LegalEntityBase
{    
	#[ORM\Column(type: "boolean")]
	private $isSupplier;
	
	#[ORM\Column(type: "boolean")]
	private $isClient;
	
	#[ORM\OneToMany(targetEntity: \App\Entity\Invoice\Invoice::class, mappedBy: "recepient", orphanRemoval: false)]
	#[ORM\OrderBy(["dateOfIssue" => "DESC", "number" => "DESC"])]
	private $invoices;
	
	#[ORM\OneToMany(targetEntity: \App\Entity\IncomingInvoice\IncomingInvoice::class, mappedBy: "issuer", orphanRemoval: false)]
	#[ORM\OrderBy(["dateOfIssue" => "DESC", "number" => "DESC"])]
	private $incomingInvoices;

	#[ORM\OneToMany(targetEntity: PartnerEmail::class, mappedBy: 'partner', cascade: ['persist', 'remove'], orphanRemoval: true)]
	private $extraEmails;
	
	public function __construct(CreatePartnerCommand $c, User $user)
	{
		parent::__construct($user);
		$this->code = $c->code;
		$this->name = $c->name;
		$this->taxNumber = $c->taxNumber;
		$this->taxable = $c->taxable;
		$this->address = $c->address;
		if($c->shortName)
			$this->shortName = $c->shortName;
		if($c->www)
			$this->www = $c->www;
		if($c->email)
			$this->email = $c->email;
		if($c->phone)
			$this->phone = $c->phone;
		if($c->mobile)
			$this->mobile = $c->mobile;
		if($c->accountNumber)
			$this->accountNumber = $c->accountNumber;
		if($c->bic)
			$this->bic = $c->bic;
		$this->isClient = $c->isClient;
		$this->isSupplier = $c->isSupplier;
		$this->invoices = new ArrayCollection();
		$this->incomingInvoices = new ArrayCollection();
		$this->extraEmails = new ArrayCollection();
		$this->syncExtraEmails($c->extraEmailCommands ?? [], $user);
	}
	
	public function update (UpdatePartnerCommand $c, User $user): Partner
	{
		parent::updateBase($user);
		if($c->name != null && $c->name != $this->name)
			$this->name = $c->name;
		if($c->taxNumber != null && $c->taxNumber != $this->taxNumber)
			$this->taxNumber = $c->taxNumber;
		if($c->taxable != null && $c->taxable != $this->taxable)
			$this->taxable = $c->taxable;
		if($c->address != null && $c->address != $this->address)
			$this->address = $c->address;
		if($c->shortName != null && $c->shortName != $this->shortName)
			$this->shortName = $c->shortName;
		if($c->www != null && $c->www != $this->www)
			$this->www = $c->www;
		if($c->email != null && $c->email != $this->email)
			$this->email = $c->email;
		if($c->phone != null && $c->phone != $this->phone)
			$this->phone = $c->phone;
		if($c->mobile != null && $c->mobile != $this->mobile)
			$this->mobile = $c->mobile;
		if($c->accountNumber != null && $c->accountNumber != $this->accountNumber)
			$this->accountNumber = $c->accountNumber;
		if($c->bic != null && $c->bic != $this->bic)
			$this->bic = $c->bic;
		if($c->isClient != null && $c->isClient != $this->isClient)
			$this->isClient = $c->isClient;
		if($c->isSupplier != null && $c->isSupplier != $this->isSupplier)
			$this->isSupplier = $c->isSupplier;
		$this->syncExtraEmails($c->extraEmailCommands ?? [], $user);
									
		return $this;
	}
	
	/**
	 *
	 * @param object $to
	 * @return object
	 */
	public function mapTo($to)
	{
		if ($to instanceof UpdatePartnerCommand)
		{
			$reflect = new \ReflectionClass($this);
			$props  = $reflect->getProperties();
			foreach($props as $prop)
			{
				$name = $prop->getName();
				if(property_exists($to, $name) && $name !== 'extraEmails')
				{
					$to->$name = $this->$name;
				}
			}
			$to->extraEmailCommands = [];
			foreach ($this->extraEmails as $extra) {
				$cmd = new CreatePartnerEmailCommand();
				$extra->mapTo($cmd);
				$to->extraEmailCommands[] = $cmd;
			}
		}
		else
		{
			throw(new \Exception('cant map ' . get_class($this) . ' to ' . get_class($to)));
			return $to;
		}
	}
	
	public function isClient(): bool
	{
		return $this->isClient;
	}
	
	public function isSupplier(): bool
	{
		return $this->isSupplier;
	}
	
	/**
	 * @return Collection|Invoice[]
	 */
	public function getInvoices(): Collection
	{
		return $this->invoices;
	}
	
	/**
	 * @return Collection|IncomingInvoice[]
	 */
	public function getIncomingInvoices(): Collection
	{
		return $this->incomingInvoices;
	}

	/**
	 * @return Collection|PartnerEmail[]
	 */
	public function getExtraEmails(): Collection
	{
		return $this->extraEmails;
	}

	public function formatToList(): string
	{
		$parser = new RecipientListParser();
		try {
			return $parser->formatList($parser->parse((string) $this->email));
		} catch (\InvalidArgumentException) {
			return (string) $this->email;
		}
	}

	public function formatCcList(): string
	{
		$parser = new RecipientListParser();
		$addresses = [];
		foreach ($this->extraEmails as $extra) {
			$address = $this->extraToAddress($extra);
			if ($address instanceof Address) {
				$addresses[] = $address;
			}
		}

		return $parser->formatList($addresses);
	}

	/**
	 * @param list<Address> $to
	 * @param list<Address> $cc
	 */
	public function applyRecipientMerge(array $to, array $cc, User $user): void
	{
		$parser = new RecipientListParser();
		parent::updateBase($user);
		if ($to !== []) {
			$this->email = $parser->formatList($to);
		}
		foreach ($cc as $address) {
			$existing = $this->findExtraByMailbox($address->getAddress());
			if ($existing instanceof PartnerEmail) {
				$existing->fillNameIfEmpty($address->getName(), $user);
				continue;
			}
			if ($this->hasMailbox($address->getAddress())) {
				continue;
			}
			$cmd = new CreatePartnerEmailCommand();
			$cmd->email = $address->getAddress();
			$cmd->name = $address->getName() !== '' ? $address->getName() : null;
			$this->createExtraEmail($cmd, $user);
		}
	}

	/**
	 * @param list<Address> $to
	 * @param list<Address> $cc
	 */
	public function emailsDifferFrom(array $to, array $cc): bool
	{
		$parser = new RecipientListParser();
		$primary = [];
		try {
			$primary = $parser->parse((string) $this->email);
		} catch (\InvalidArgumentException) {
			return true;
		}
		if ($parser->mailboxSet($primary) !== $parser->mailboxSet($to)) {
			return true;
		}
		$extras = [];
		foreach ($this->extraEmails as $extra) {
			$address = $this->extraToAddress($extra);
			if (!$address instanceof Address) {
				return true;
			}
			$extras[] = $address;
		}

		return $parser->mailboxSet($extras) !== $parser->mailboxSet($cc);
	}

	public function hasMailbox(string $email): bool
	{
		$needle = strtolower(trim($email));
		$parser = new RecipientListParser();
		try {
			foreach ($parser->parse((string) $this->email) as $address) {
				if (strtolower($address->getAddress()) === $needle) {
					return true;
				}
			}
		} catch (\InvalidArgumentException) {
		}

		return $this->findExtraByMailbox($email) instanceof PartnerEmail;
	}

	public function createExtraEmail(CreatePartnerEmailCommand $c, User $user): PartnerEmail
	{
		$email = new PartnerEmail($c, $this, $user);
		$this->extraEmails->add($email);

		return $email;
	}

	/**
	 * @param list<CreatePartnerEmailCommand> $commands
	 */
	private function syncExtraEmails(array $commands, User $user): void
	{
		$keep = new ArrayCollection();
		foreach ($commands as $cmd) {
			if (!trim((string) ($cmd->email ?? ''))) {
				continue;
			}
			$existing = null;
			if ($cmd->id) {
				foreach ($this->extraEmails as $extra) {
					if ((string) $extra->getId() === (string) $cmd->id) {
						$existing = $extra;
						break;
					}
				}
			}
			if ($existing instanceof PartnerEmail) {
				$keep->add($existing->update($cmd, $user));
			} else {
				$keep->add($this->createExtraEmail($cmd, $user));
			}
		}
		foreach ($this->extraEmails as $extra) {
			if (!$keep->contains($extra)) {
				$this->extraEmails->removeElement($extra);
			}
		}
	}

	private function extraToAddress(PartnerEmail $extra): ?Address
	{
		$raw = trim((string) $extra->getEmail());
		if ($raw === '') {
			return null;
		}
		try {
			$parsed = (new RecipientListParser())->parse($raw);
		} catch (\InvalidArgumentException) {
			return null;
		}
		if ($parsed === []) {
			return null;
		}
		$address = $parsed[0];
		$display = trim((string) $extra->getName());
		if ($display === '') {
			$display = trim((string) $extra->getRole());
		}
		if ($display !== '' && $address->getName() === '') {
			return new Address($address->getAddress(), $display);
		}

		return $address;
	}

	private function findExtraByMailbox(string $email): ?PartnerEmail
	{
		$needle = strtolower(trim($email));
		foreach ($this->extraEmails as $extra) {
			if (strtolower($extra->getEmail()) === $needle) {
				return $extra;
			}
		}

		return null;
	}
}
