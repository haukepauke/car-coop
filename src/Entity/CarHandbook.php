<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CarHandbookRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CarHandbookRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['car' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER")'),
        new Get(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
    ],
    normalizationContext: ['groups' => ['car_handbook:read', 'car:read']],
    order: ['updatedAt' => 'DESC', 'id' => 'DESC'],
)]
class CarHandbook
{
    public const MAX_CONTENT_LENGTH = 50000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['car_handbook:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'handbook', targetEntity: Car::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    #[Groups(['car_handbook:read'])]
    private Car $car;

    #[ORM\Column(type: 'text')]
    #[Assert\Length(max: CarHandbook::MAX_CONTENT_LENGTH)]
    #[Groups(['car_handbook:read'])]
    private string $content = '';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['car_handbook:read'])]
    private ?array $photos = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['car_handbook:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['car_handbook:read'])]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

        if ($car->getHandbook() !== $this) {
            $car->setHandbook($this);
        }

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->touch();

        return $this;
    }

    public function getPhotos(): array
    {
        return $this->photos ?? [];
    }

    public function setPhotos(array $photos): self
    {
        $uniquePhotos = array_values(array_unique(array_filter($photos)));
        $this->photos = $uniquePhotos !== [] ? $uniquePhotos : null;
        $this->touch();

        return $this;
    }

    public function hasPhoto(string $filename): bool
    {
        return in_array($filename, $this->getPhotos(), true);
    }

    #[Groups(['car_handbook:read'])]
    public function getAttachmentUrls(): array
    {
        if ($this->id === null) {
            return [];
        }

        return array_map(
            fn(string $filename) => sprintf('/api/car_handbooks/%d/attachments/%s', $this->id, rawurlencode($filename)),
            $this->getPhotos(),
        );
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }
}
