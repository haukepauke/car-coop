<?php

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    public const TYPES = ['vacation', 'transport', 'service'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive()]
    private $startMileage;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Expression('this.getEndMileage() > this.getStartMileage()', message: 'Did you drive backwards?!')]
    private $endMileage;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank()]
    private $startDate;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\Expression('this.getEndDate() === null || this.getEndDate() > this.getStartDate()', message: 'End date has to be empty or to be after the startdate')]
    private $endDate;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $car;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $user;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\PositiveOrZero()]
    private $costs;

    #[ORM\Column(type: 'string', length: 30)]
    #[Assert\Choice(Trip::TYPES)]
    private $type;

    #[ORM\Column(type: 'text', nullable: true)]
    private $comment;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCosts(): ?float
    {
        if ($this->isCompleted()) {
            return $this->costs;
        }

        return 0.0;
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
        if ($this->isCompleted()) {
            return $this->getEndMileage() - $this->getStartMileage();
        }

        return 0;
    }

    public function isCompleted(): bool
    {
        if (null !== $this->getEndDate() && null !== $this->getEndMileage()) {
            return true;
        }

        return false;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }
}
