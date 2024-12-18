<?php

namespace App\Entity;

use App\Repository\UserTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * TODO: Refactor class name to UserGroup without breaking doctrine migrations.
 */
#[ORM\Entity(repositoryClass: UserTypeRepository::class)]
class UserType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 30)]
    #[Assert\NotBlank()]
    private $name;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'userTypes')]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\NotBlank()]
    private $car;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'userTypes')]
    private $users;

    #[ORM\Column(type: 'float')]
    #[Assert\PositiveOrZero()]
    private $pricePerUnit;

    #[ORM\OneToMany(mappedBy: 'userType', targetEntity: Invitation::class, orphanRemoval: true)]
    private $invitations;

    #[ORM\Column(type: 'boolean')]
    private $active = true;

    #[ORM\Column(type: 'boolean')]
    private $admin = true;

    #[ORM\Column(type: 'boolean')]
    private $fixed = false;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): self
    {
        $this->car = $car;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->users->removeElement($user);

        return $this;
    }

    public function getPricePerUnit(): ?float
    {
        return $this->pricePerUnit;
    }

    public function setPricePerUnit(float $pricePerUnit): self
    {
        $this->pricePerUnit = $pricePerUnit;

        return $this;
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
            $invitation->setUserType($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): self
    {
        if ($this->invitations->removeElement($invitation)) {
            // set the owning side to null (unless already changed)
            if ($invitation->getUserType() === $this) {
                $invitation->getUserType(null);
            }
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): self
    {
        $this->admin = $admin;

        return $this;
    }

    public function isFixed(): bool
    {
        return $this->fixed;
    }

    public function setFixed(bool $fixed): self
    {
        $this->fixed = $fixed;

        return $this;
    }

    // #[Assert\Callback()]
    // public function validate(ExecutionContextInterface $context, $payload) {
    //     //check if the user is in another group already
    //     foreach($this->users as $user){
    //         $groupCount = 0;
    //         $otherGroup = '';
    //         foreach($user->getUserTypes() as $type){
    //             if($user->getCar() === $this->getCar()){
    //                 $groupCount++;
    //             }
    //             if($type != $this){
    //                 $otherGroup = $type->getName();
    //             }
    //         }
    //         if($groupCount > 1){
    //             $context->buildViolation('User ' . 
    //                 $user->getEmail() . 
    //                 ' can not be in more than one group. The user is also in the group "' . 
    //                 $otherGroup . '"')
    //                 ->atPath('user')
    //                 ->addViolation();
    //         }
    //     }
    //}
}
