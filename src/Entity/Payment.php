<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    public const TYPES = ['cash', 'paypal', 'banktransfer', 'other'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'date')]
    private $date;

    #[ORM\Column(type: 'float')]
    #[Assert\Positive()]
    private $amount;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(Payment::TYPES)]
    private $type;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'paymentsMade')]
    #[ORM\JoinColumn(nullable: false)]
    private $fromUser;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'paymentsReceived')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Assert\Expression('this.getFromUser() !== this.getToUser()', message: 'Payment sender and receiver must be different users')]
    private $toUser;

    #[ORM\Column(type: 'text', nullable: true)]
    private $comment;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $car;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function setFromUser(?User $fromUser): self
    {
        $this->fromUser = $fromUser;

        return $this;
    }

    public function getToUser(): ?User
    {
        return $this->toUser;
    }

    public function setToUser(?User $toUser): self
    {
        $this->toUser = $toUser;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): self
    {
        $this->car = $car;

        return $this;
    }
}
