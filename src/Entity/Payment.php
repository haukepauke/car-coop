<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\PaymentRepository;
use App\State\EditorStateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER")'),
        new Get(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
        new Post(
            security: 'is_granted("ROLE_USER")',
            securityPostDenormalize: 'object.getCar().hasUser(user)',
            processor: EditorStateProcessor::class,
        ),
        new Put(
            security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)',
            processor: EditorStateProcessor::class,
        ),
        new Delete(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
    ],
    normalizationContext: ['groups' => ['payment:read']],
    denormalizationContext: ['groups' => ['payment:write']],
    order: ['date' => 'DESC'],
)]
class Payment
{
    public const TYPES = ['cash', 'paypal', 'banktransfer', 'other'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['payment:read'])]
    private $id;

    #[ORM\Column(type: 'date')]
    #[Groups(['payment:read', 'payment:write'])]
    private $date;

    #[ORM\Column(type: 'float')]
    #[Assert\Positive()]
    #[Groups(['payment:read', 'payment:write'])]
    private $amount;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(Payment::TYPES)]
    #[Groups(['payment:read', 'payment:write'])]
    private $type;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'paymentsMade')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:write'])]
    private $fromUser;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'paymentsReceived')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Assert\Expression('this.getFromUser() !== this.getToUser()', message: 'Payment sender and receiver must be different users')]
    #[Groups(['payment:read', 'payment:write'])]
    private $toUser;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private $comment;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Groups(['payment:read', 'payment:write'])]
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
