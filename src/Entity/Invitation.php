<?php

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvitationRepository::class)]
class Invitation
{
    public const STATUS = ['new', 'expired'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank()]
    private $hash;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\Choice(Invitation::STATUS)]
    private $status;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank()]
    private $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $createdBy;

    #[ORM\ManyToOne(targetEntity: UserType::class, inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $userType;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\Email()]
    private $email;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUserType(): ?UserType
    {
        return $this->userType;
    }

    public function setUserType(?UserType $userType): self
    {
        $this->userType = $userType;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
}
