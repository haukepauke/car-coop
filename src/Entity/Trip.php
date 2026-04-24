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
use App\Repository\TripRepository;
use App\State\TripStateProcessor;
use App\Validator\IsValidTripDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['car' => 'exact'])]
#[IsValidTripDate]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER")'),
        new Get(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
        new Post(
            security: 'is_granted("ROLE_USER")',
            securityPostDenormalize: 'object.getCar().hasUser(user)',
            processor: TripStateProcessor::class,
        ),
        new Put(
            security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)',
            securityPostDenormalize: 'object.getCar().hasUser(user)',
            processor: TripStateProcessor::class,
        ),
        new Delete(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
    ],
    normalizationContext: ['groups' => ['trip:read', 'user:read']],
    denormalizationContext: ['groups' => ['trip:write']],
    order: ['endDate' => 'DESC', 'id' => 'DESC'],
)]
class Trip
{
    public const TYPES = ['vacation', 'transport', 'other', 'service_free', 'other_free', 'placeholder_free'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['trip:read'])]
    private $id;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive()]
    #[Groups(['trip:read', 'trip:write'])]
    private $startMileage;

    #[ORM\Column(type: 'integer')]
    #[Assert\Expression('this.getEndMileage() > this.getStartMileage()', message: 'trip.backwards')]
    #[Groups(['trip:read', 'trip:write'])]
    private $endMileage;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank()]
    #[Groups(['trip:read', 'trip:write'])]
    private $startDate;

    #[ORM\Column(type: 'date')]
    #[Assert\Expression('this.getEndDate() >= this.getStartDate()', message: 'trip.end_date_before_start')]
    #[Groups(['trip:read', 'trip:write'])]
    private $endDate;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    #[Groups(['trip:read', 'trip:write'])]
    private $car;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'trips')]
    #[Assert\Count(min: 1)]
    #[Groups(['trip:read', 'trip:write'])]
    private Collection $users;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\PositiveOrZero()]
    #[Groups(['trip:read', 'trip:write'])]
    private $costs;

    #[ORM\Column(type: 'string', length: 30)]
    #[Assert\Choice(Trip::TYPES)]
    #[Groups(['trip:read', 'trip:write'])]
    private $type;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['trip:read', 'trip:write'])]
    private $comment;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['trip:read'])]
    private $editor;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getStartDate().
            ' - '.
            $this->getEndDate().
            ': '.
            $this->getStartMileage().
            ', '.
            $this->getEndMileage();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartMileage(): ?int
    {
        return $this->startMileage;
    }

    public function setStartMileage(int $startMileage): self
    {
        $this->startMileage = $startMileage;

        return $this;
    }

    public function getEndMileage(): ?int
    {
        return $this->endMileage;
    }

    public function setEndMileage(int $endMileage): self
    {
        $this->endMileage = $endMileage;

        return $this;
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

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

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

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->users->removeElement($user);

        return $this;
    }

    public function getCosts(): ?float
    {
        return $this->costs ?? 0.0;
    }

    public function setCosts(?float $costs): self
    {
        $this->costs = $costs;

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

    public function getMileage(): int
    {
        return $this->getEndMileage() - $this->getStartMileage();
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getComment(): string
    {
        if (null === $this->comment) {
            return '';
        }

        return $this->comment;
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
