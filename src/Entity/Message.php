<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Car::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Car $car;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isSticky = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = null;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSticky(): bool
    {
        return $this->isSticky;
    }

    public function setIsSticky(bool $isSticky): self
    {
        $this->isSticky = $isSticky;
        return $this;
    }

    public function getPhotos(): array
    {
        return $this->photos ?? [];
    }

    public function setPhotos(array $photos): self
    {
        $this->photos = $photos ?: null;
        return $this;
    }

    /**
     * For system messages (author === null), decodes the JSON stored in content
     * and returns ['key' => '...', 'params' => [...]]. Returns null for user messages.
     */
    public function getSystemData(): ?array
    {
        if ($this->author !== null) {
            return null;
        }
        $data = json_decode($this->content, true);
        return is_array($data) && isset($data['key']) ? $data : null;
    }
}
