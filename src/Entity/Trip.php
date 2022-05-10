<?php

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    private $startMileage;

    #[ORM\Column(type: 'integer')]
    private $endMileage;

    #[ORM\Column(type: 'date')]
    private $startDate;

    #[ORM\Column(type: 'date', nullable: true)]
    private $endDate;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private $car;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\Column(type: 'float', nullable: true)]
    private $costs;

    #[ORM\Column(type: 'string', length: 30)]
    private $type;

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
        return $this->costs;
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
}
