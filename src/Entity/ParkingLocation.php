<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ParkingLocationRepository;
use App\State\ParkingLocationStateProcessor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ParkingLocationRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['car' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER")'),
        new Get(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
        new Post(
            security: 'is_granted("ROLE_USER")',
            securityPostDenormalize: 'object.getCar().hasUser(user)',
            processor: ParkingLocationStateProcessor::class,
        ),
        new Put(
            security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)',
            processor: ParkingLocationStateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['parking:read']],
    denormalizationContext: ['groups' => ['parking:write']],
    order: ['createdAt' => 'DESC'],
)]
class ParkingLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['parking:read'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'parkingLocations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['parking:read', 'parking:write'])]
    private Car $car;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['parking:read'])]
    private User $user;

    #[ORM\Column(type: 'float')]
    #[Groups(['parking:read', 'parking:write'])]
    private float $latitude;

    #[ORM\Column(type: 'float')]
    #[Groups(['parking:read', 'parking:write'])]
    private float $longitude;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['parking:read'])]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCar(): Car
    {
        return $this->car;
    }

    public function setCar(Car $car): self
    {
        $this->car = $car;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
