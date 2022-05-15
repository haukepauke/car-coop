<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    public const STATUS = ['fixed', 'maybe'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'datetime')]
    #[Assert\GreaterThan(value: 'today', message: 'Bookings can not be made for the past')]
    private $startDate;

    #[ORM\Column(type: 'datetime')]
    #[Assert\Expression('this.getEndDate() > this.getStartDate()', message: 'End date must be after start date of booking')]
    private $endDate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $title;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $user;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank()]
    private $car;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(Booking::STATUS)]
    private $status;

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
}
