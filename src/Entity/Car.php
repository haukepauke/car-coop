<?php

namespace App\Entity;

use App\Repository\CarRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CarRepository::class)]
class Car
{
    public const MILEAGE_UNITS = ['km', 'mi'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 30)]
    #[Assert\NotBlank()]
    private $name;

    #[ORM\Column(type: 'string', length: 15, nullable: true)]
    private $licensePlate;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero(message: 'Please enter a positive value')]
    private $mileage;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $make;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $vendor;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: Trip::class, orphanRemoval: true)]
    private $trips;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: Expense::class, orphanRemoval: true)]
    private $expenses;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: UserType::class, orphanRemoval: true)]
    private $userTypes;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\Choice(Car::MILEAGE_UNITS)]
    private $milageUnit;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: Payment::class, orphanRemoval: true)]
    private $payments;

    #[ORM\OneToMany(mappedBy: 'Car', targetEntity: Booking::class, orphanRemoval: true)]
    private $bookings;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $profilePicturePath;

    public function __construct()
    {
        $this->trips = new ArrayCollection();
        $this->expenses = new ArrayCollection();
        $this->userTypes = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->bookings = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLicensePlate(): ?string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): self
    {
        $this->licensePlate = $licensePlate;

        return $this;
    }

    public function getMileage(): ?int
    {
        return $this->mileage;
    }

    public function setMileage(int $mileage): self
    {
        $this->mileage = $mileage;

        return $this;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function setMake(string $make): self
    {
        $this->make = $make;

        return $this;
    }

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    public function setVendor(string $vendor): self
    {
        $this->vendor = $vendor;

        return $this;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): self
    {
        if (!$this->trips->contains($trip)) {
            $this->trips[] = $trip;
            $trip->setCar($this);
        }

        return $this;
    }

    public function removeTrip(Trip $trip): self
    {
        if ($this->trips->removeElement($trip)) {
            // set the owning side to null (unless already changed)
            if ($trip->getCar() === $this) {
                $trip->setCar(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Expense>
     */
    public function getExpenses(): Collection
    {
        return $this->expenses;
    }

    public function addExpense(Expense $expense): self
    {
        if (!$this->expenses->contains($expense)) {
            $this->expenses[] = $expense;
            $expense->setCar($this);
        }

        return $this;
    }

    public function removeExpense(Expense $expense): self
    {
        if ($this->expenses->removeElement($expense)) {
            // set the owning side to null (unless already changed)
            if ($expense->getCar() === $this) {
                $expense->setCar(null);
            }
        }

        return $this;
    }

    /**
     * Get the value of name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the value of name.
     *
     * @param mixed $name
     */
    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, UserType>
     */
    public function getUserTypes(): Collection
    {
        return $this->userTypes;
    }

    public function addUserType(UserType $userType): self
    {
        if (!$this->userTypes->contains($userType)) {
            $this->userTypes[] = $userType;
            $userType->setCar($this);
        }

        return $this;
    }

    public function removeUserType(UserType $userType): self
    {
        if ($this->userTypes->removeElement($userType)) {
            // set the owning side to null (unless already changed)
            if ($userType->getCar() === $this) {
                $userType->setCar(null);
            }
        }

        return $this;
    }

    public function getMilageUnit(): ?string
    {
        return $this->milageUnit;
    }

    public function setMilageUnit(string $milageUnit): self
    {
        $this->milageUnit = $milageUnit;

        return $this;
    }

    /**
     * Returns true if the user given is also a user of the car.
     */
    public function hasUser(User $user)
    {
        foreach ($this->getUserTypes() as $userType) {
            foreach ($userType->getUsers() as $carUsers) {
                if ($user->getId() === $carUsers->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments[] = $payment;
            $payment->setCar($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getCar() === $this) {
                $payment->setCar(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings[] = $booking;
            $booking->setCar($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getCar() === $this) {
                $booking->setCar(null);
            }
        }

        return $this;
    }

    public function getProfilePicturePath(): ?string
    {
        return $this->profilePicturePath;
    }

    public function setProfilePicturePath(?string $profilePicturePath): self
    {
        $this->profilePicturePath = $profilePicturePath;

        return $this;
    }

    public function getUsers(): ArrayCollection
    {
        $users = new ArrayCollection();

        foreach ($this->getUserTypes() as $userType) {
            foreach ($userType->getUsers() as $user) {
                $users->add($user);
            }
        }

        return $users;
    }

    public function getActiveUsers(): ArrayCollection
    {
        $activeUsers = new ArrayCollection();
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user->isActive()) {
                $activeUsers->add($user);
            }
        }

        return $activeUsers;
    }

    public function getDistanceTravelled(\DateTime $start, \DateTime $end): int
    {
        $distance = 0;
        foreach ($this->trips as $trip) {
            if ($trip->isCompleted() && $trip->getStartDate() > $start && $trip->getEndDate() < $end) {
                $distance += $trip->getMileage();
            }
        }

        return $distance;
    }

    public function getMoneySpent(\DateTime $start = null, \DateTime $end = null, string $type = null): int
    {
        $moneySpent = 0;

        if (null === $start) {
            $start = new \DateTime('2000-01-01');
        }

        if (null === $end) {
            $end = new \DateTime();
        }

        foreach ($this->expenses as $expense) {
            if (
                $expense->getDate() > $start && 
                $expense->getDate() < $end &&
                ($type === null || $type == $expense->getType())) {
                $moneySpent += $expense->getAmount();
            }
        }

        return $moneySpent;
    }
}
