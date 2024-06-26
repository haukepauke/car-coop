<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const LOCALES = ['en', 'de'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\Email()]
    private $email;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank()]
    private $name;

    #[ORM\Column(type: 'json')]
    private $roles = [];

    #[ORM\Column(type: 'string')]
    #[Assert\NotCompromisedPassword(message: 'This password is insecure, please chose a better one')]
    private $password;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Expense::class, orphanRemoval: true)]
    private $expenses;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Trip::class, orphanRemoval: true)]
    private $trips;

    #[ORM\ManyToMany(targetEntity: UserType::class, mappedBy: 'users')]
    private $userTypes;

    #[ORM\Column(type: 'boolean')]
    private $isVerified = false;

    #[ORM\OneToMany(mappedBy: 'fromUser', targetEntity: Payment::class, orphanRemoval: true)]
    private $paymentsMade;

    #[ORM\OneToMany(mappedBy: 'toUser', targetEntity: Payment::class, orphanRemoval: true)]
    private $paymentsReceived;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Invitation::class, orphanRemoval: true)]
    private $invitations;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Booking::class, orphanRemoval: true)]
    private $bookings;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private $color;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $profilePicturePath;

    #[ORM\Column(type: 'string', length: 2)]
    #[Assert\Choice(User::LOCALES)]
    private $locale;

    #[ORM\Column]
    private ?bool $notifiedOnEvents = null;

    #[ORM\Column]
    private ?bool $notifiedOnOwnEvents = null;

    public function __construct()
    {
        $this->expenses = new ArrayCollection();
        $this->trips = new ArrayCollection();
        $this->userTypes = new ArrayCollection();
        $this->paymentsMade = new ArrayCollection();
        $this->paymentsReceived = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->bookings = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getEmail();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Expense>
     */
    public function getExpenses(): Collection
    {
        return $this->expenses;
    }

    public function getExpensesByPeriod(\DateTime $start, \DateTime $end)
    {
        $expenses = new ArrayCollection();
        foreach ($this->getExpenses() as $expense) {
            if ($expense->getDate() >= $start && $expense->getDate() <= $end) {
                $expenses->add($expense);
            }
        }

        return $expenses;
    }

    public function addExpense(Expense $expense): self
    {
        if (!$this->expenses->contains($expense)) {
            $this->expenses[] = $expense;
            $expense->setUser($this);
        }

        return $this;
    }

    public function removeExpense(Expense $expense): self
    {
        if ($this->expenses->removeElement($expense)) {
            // set the owning side to null (unless already changed)
            if ($expense->getUser() === $this) {
                $expense->setUser(null);
            }
        }

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
            $trip->setUser($this);
        }

        return $this;
    }

    public function removeTrip(Trip $trip): self
    {
        if ($this->trips->removeElement($trip)) {
            // set the owning side to null (unless already changed)
            if ($trip->getUser() === $this) {
                $trip->setUser(null);
            }
        }

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
            $userType->addUser($this);
        }

        return $this;
    }

    public function removeUserType(UserType $userType): self
    {
        if ($this->userTypes->removeElement($userType)) {
            $userType->removeUser($this);
        }

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPaymentsMade(): Collection
    {
        return $this->paymentsMade;
    }

    public function getPaymentsMadeByPeriod(\DateTime $start, \DateTime $end)
    {
        $payments = new ArrayCollection();
        foreach ($this->getPaymentsMade() as $payment) {
            if ($payment->getDate() >= $start && $payment->getDate() <= $end) {
                $payments->add($payment);
            }
        }

        return $payments;
    }

    public function addPaymentsMade(Payment $paymentsMade): self
    {
        if (!$this->paymentsMade->contains($paymentsMade)) {
            $this->paymentsMade[] = $paymentsMade;
            $paymentsMade->setFromUser($this);
        }

        return $this;
    }

    public function removePaymentsMade(Payment $paymentsMade): self
    {
        if ($this->paymentsMade->removeElement($paymentsMade)) {
            // set the owning side to null (unless already changed)
            if ($paymentsMade->getFromUser() === $this) {
                $paymentsMade->setFromUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPaymentsReceived(): Collection
    {
        return $this->paymentsReceived;
    }

    public function getPaymentsReceivedByPeriod(\DateTime $start, \DateTime $end)
    {
        $payments = new ArrayCollection();
        foreach ($this->getPaymentsReceived() as $payment) {
            if ($payment->getDate() >= $start && $payment->getDate() <= $end) {
                $payments->add($payment);
            }
        }

        return $payments;
    }

    public function addPaymentsReceived(Payment $paymentsReceived): self
    {
        if (!$this->paymentsReceived->contains($paymentsReceived)) {
            $this->paymentsReceived[] = $paymentsReceived;
            $paymentsReceived->setToUser($this);
        }

        return $this;
    }

    public function removePaymentsReceived(Payment $paymentsReceived): self
    {
        if ($this->paymentsReceived->removeElement($paymentsReceived)) {
            // set the owning side to null (unless already changed)
            if ($paymentsReceived->getToUser() === $this) {
                $paymentsReceived->setToUser(null);
            }
        }

        return $this;
    }

    /**
     * TODO implement date time filter for money spent.
     */
    public function getMoneySpent(?\DateTime $start = null, ?\DateTime $end = null)
    {
        $amount = 0;

        if (null === $start) {
            $start = new \DateTime('2000-01-01');
        }

        if (null === $end) {
            $end = new \DateTime();
        }

        foreach ($this->getExpensesByPeriod($start, $end) as $expense) {
            $amount += $expense->getAmount();
        }

        foreach ($this->getPaymentsMadeByPeriod($start, $end) as $payment) {
            $amount += $payment->getAmount();
        }

        foreach ($this->getPaymentsReceivedByPeriod($start, $end) as $payment) {
            $amount -= $payment->getAmount();
        }

        return $amount;
    }

    public function getTripMileage(?\DateTime $start = null, ?\DateTime $end = null): int
    {
        $mileage = 0;

        if (null === $start) {
            $start = new \DateTime('2000-01-01');
        }

        if (null === $end) {
            $end = new \DateTime();
        }

        foreach ($this->getTrips() as $trip) {
            if ($trip->isCompleted() && $trip->getStartDate() > $start && $trip->getEndDate() < $end) {
                $mileage += $trip->getMileage();
            }
        }

        return $mileage;
    }

    public function getBalance()
    {
        $balance = 0.0;

        foreach ($this->getTrips() as $trip) {
            if ($trip->isCompleted()) {
                $balance -= $trip->getCosts();
            }
        }

        foreach ($this->getExpenses() as $expense) {
            $balance += $expense->getAmount();
        }

        foreach ($this->paymentsMade as $payment) {
            $balance += $payment->getAmount();
        }

        foreach ($this->getPaymentsReceived() as $payment) {
            $balance -= $payment->getAmount();
        }

        return $balance;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $invitation): self
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations[] = $invitation;
            $invitation->setCreatedBy($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): self
    {
        if ($this->invitations->removeElement($invitation)) {
            // set the owning side to null (unless already changed)
            if ($invitation->getCreatedBy() === $this) {
                $invitation->setCreatedBy(null);
            }
        }

        return $this;
    }

    public function getCar(): ?Car
    {
        $usergroup = $this->getUserTypes()->get(0);

        if (null === $usergroup) {
            return null;
        }

        return $usergroup->getCar();
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
            $booking->setUser($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getUser() === $this) {
                $booking->setUser(null);
            }
        }

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

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

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function hasEntries(): bool
    {
        if ($this->getExpenses()->count() > 0
            || $this->getPaymentsMade()->count() > 0
            || $this->getPaymentsReceived()->count() > 0
            || $this->getTrips()->count() > 0) {
            return true;
        }

        return false;
    }

    public function deactivate(): void
    {
        // add usertype for deactivated users
        foreach ($this->getCar()->getUserTypes() as $type) {
            if (!$type->isActive()) {
                $this->addUserType($type);
            }
        }

        // remove all other user types
        foreach ($this->getUserTypes() as $type) {
            if ($type->isActive()) {
                $this->removeUserType($type);
            }
        }

        $nUserTypes = $this->getUserTypes()->count();
        if (1 !== $nUserTypes) {
            throw new \Exception('Usertype misconfiguration. Expected one user type after user deactivation, found '.$nUserTypes);
        }
    }

    public function anonymize(): void
    {
        // anonymize email
        list($first, $last) = explode('@', $this->email);
        $first = str_replace(substr($first, '3'), str_repeat('x', strlen($first) - 3), $first);
        $last = explode('.', $last);
        $last_domain = str_replace(substr($last['0'], '1'), str_repeat('x', strlen($last['0']) - 1), $last['0']);
        $this->email = $first.'@'.$last_domain.'.'.$last['1'];

        // anonymize name
        $this->name = str_replace(substr($this->name, '1'), str_repeat('x', strlen($this->name) - 1), $this->name);
    }

    public function isActive(): bool
    {
        foreach ($this->getUserTypes() as $type) {
            if ($type->isActive()) {
                return true;
            }

            return false;
        }

        return true;
    }

    public function isNotifiedOnEvents(): ?bool
    {
        return $this->notifiedOnEvents;
    }

    public function setNotifiedOnEvents(bool $notifiedOnEvents): self
    {
        $this->notifiedOnEvents = $notifiedOnEvents;

        return $this;
    }

    public function isNotifiedOnOwnEvents(): ?bool
    {
        return $this->notifiedOnOwnEvents;
    }

    public function setNotifiedOnOwnEvents(bool $notifiedOnOwnEvents): self
    {
        $this->notifiedOnOwnEvents = $notifiedOnOwnEvents;

        return $this;
    }
}
