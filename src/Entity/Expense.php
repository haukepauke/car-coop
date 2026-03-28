<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ExpenseRepository;
use App\State\EditorStateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
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
    normalizationContext: ['groups' => ['expense:read', 'user:read']],
    denormalizationContext: ['groups' => ['expense:write']],
    order: ['date' => 'DESC'],
)]
class Expense
{
    public const TYPES = ['fuel', 'charging', 'service', 'other'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['expense:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\Choice(Expense::TYPES)]
    #[Groups(['expense:read', 'expense:write'])]
    private $type;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank()]
    #[Groups(['expense:read', 'expense:write'])]
    private $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['expense:read', 'expense:write'])]
    private $comment;

    #[ORM\Column(type: 'float')]
    #[Assert\Positive(message: 'expense.amount_required')]
    #[Groups(['expense:read', 'expense:write'])]
    private $amount;

    #[ORM\Column(type: 'date')]
    #[Groups(['expense:read', 'expense:write'])]
    private $date;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'expenses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Groups(['expense:read', 'expense:write'])]
    private $car;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'expenses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Groups(['expense:read', 'expense:write'])]
    private $user;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['expense:read'])]
    private $editor;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
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

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): self
    {
        $this->car = $car;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEditor(): ?User
    {
        return $this->editor;
    }

    public function setEditor(?User $editor): self
    {
        $this->editor = $editor;

        return $this;
    }
}
