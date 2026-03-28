<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\BookingRepository;
use App\State\EditorStateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['car' => 'exact'])]
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
    normalizationContext: ['groups' => ['booking:read', 'user:read']],
    denormalizationContext: ['groups' => ['booking:write']],
    order: ['startDate' => 'ASC'],
)]
class Booking
{
    public const STATUS = ['fixed', 'maybe'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['booking:read'])]
    private $id;

    #[ORM\Column(type: 'datetime')]
    #[Assert\GreaterThan(value: 'today', message: 'booking.not_in_past')]
    #[Groups(['booking:read', 'booking:write'])]
    private $startDate;

    #[ORM\Column(type: 'datetime')]
    #[Assert\Expression('this.getEndDate() > this.getStartDate()', message: 'booking.end_before_start')]
    #[Groups(['booking:read', 'booking:write'])]
    private $endDate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['booking:read', 'booking:write'])]
    private $title;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Groups(['booking:read', 'booking:write'])]
    private $user;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Groups(['booking:read', 'booking:write'])]
    private $car;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(Booking::STATUS)]
    #[Groups(['booking:read', 'booking:write'])]
    private $status;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['booking:read'])]
    private $editor;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

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

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): self
    {
        $this->car = $car;

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
