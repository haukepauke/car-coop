<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private $email;

    #[ORM\Column(type: 'string', length: 50)]
    private $name;

    #[ORM\Column(type: 'json')]
    private $roles = [];

    #[ORM\Column(type: 'string')]
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

    public function __construct()
    {
        $this->expenses = new ArrayCollection();
        $this->trips = new ArrayCollection();
        $this->userTypes = new ArrayCollection();
        $this->paymentsMade = new ArrayCollection();
        $this->paymentsReceived = new ArrayCollection();
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

    public function getMoneySpent()
    {
        $amount = 0;

        foreach ($this->getExpenses() as $expense) {
            $amount += $expense->getAmount();
        }

        foreach ($this->paymentsMade as $payment) {
            $amount += $payment->getAmount();
        }

        foreach ($this->getPaymentsReceived() as $payment) {
            $amount -= $payment->getAmount();
        }

        return $amount;
    }

    public function getTripMileage()
    {
        $mileage = 0;

        foreach ($this->getTrips() as $trip) {
            $mileage += $trip->getMileage();
        }

        return $mileage;
    }

    public function getBalance()
    {
        $balance = 0.0;

        foreach ($this->getTrips() as $trip) {
            $balance -= $trip->getCosts();
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
}
