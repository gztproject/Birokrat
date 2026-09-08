<?php

namespace App\Entity\Organization;

use App\Entity\Base\AggregateBase;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Organization\PartnerEmailRepository::class)]
class PartnerEmail extends AggregateBase
{
    #[ORM\ManyToOne(targetEntity: Partner::class, inversedBy: 'extraEmails')]
    #[ORM\JoinColumn(nullable: false)]
    private $partner;

    #[ORM\Column(type: 'string', length: 255)]
    private $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $role;

    public function __construct(CreatePartnerEmailCommand $c, Partner $partner, User $user)
    {
        parent::__construct($user);
        $this->partner = $partner;
        $this->email = trim((string) $c->email);
        $this->name = $c->name !== null && $c->name !== '' ? trim((string) $c->name) : null;
        $this->role = $c->role !== null && $c->role !== '' ? trim((string) $c->role) : null;
    }

    public function update(CreatePartnerEmailCommand $c, User $user): PartnerEmail
    {
        parent::updateBase($user);
        if ($c->email !== null && $c->email !== $this->email) {
            $this->email = trim((string) $c->email);
        }
        if ($c->name !== null && $c->name !== $this->name) {
            $this->name = $c->name === '' ? null : trim((string) $c->name);
        }
        if ($c->role !== null && $c->role !== $this->role) {
            $this->role = $c->role === '' ? null : trim((string) $c->role);
        }

        return $this;
    }

    public function fillNameIfEmpty(string $name, User $user): void
    {
        $name = trim($name);
        if ($name === '' || ($this->name !== null && $this->name !== '')) {
            return;
        }
        parent::updateBase($user);
        $this->name = $name;
    }

    public function mapTo($to)
    {
        if ($to instanceof CreatePartnerEmailCommand) {
            $to->id = (string) $this->getId();
            $to->email = $this->email;
            $to->name = $this->name;
            $to->role = $this->role;

            return $to;
        }

        throw new \Exception('cant map '.get_class($this).' to '.get_class($to));
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }
}
