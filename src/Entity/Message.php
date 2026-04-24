<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\MessageApiCreateController;
use App\Repository\MessageRepository;
use App\State\MessageDeleteProcessor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['car' => 'exact'])]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER")'),
        new Get(security: 'is_granted("ROLE_USER") and object.getCar().hasUser(user)'),
        new Post(
            controller: MessageApiCreateController::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
            security: 'is_granted("ROLE_USER")',
            deserialize: false,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'car'     => ['type' => 'integer', 'description' => 'Car ID'],
                                    'content' => ['type' => 'string'],
                                    'photos'  => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'binary'], 'description' => 'JPG, PNG, GIF, or PDF attachments'],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Delete(
            security: 'is_granted("ROLE_USER") and (object.getAuthor() === user or object.getCar().isAdminUser(user))',
            processor: MessageDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['message:read', 'user:read']],
    order: ['createdAt' => 'DESC'],
)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['message:read'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: Car::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['message:read'])]
    private Car $car;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['message:read'])]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['message:read'])]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['message:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['message:read'])]
    private bool $isSticky = false;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['message:read'])]
    private ?array $photos = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['message:read'])]
    private bool $isBroadcast = false;

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

    /** Alias so the Symfony serializer exposes this as "isSticky" in JSON. */
    public function getIsSticky(): bool
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

    public function hasPhoto(string $filename): bool
    {
        return in_array($filename, $this->getPhotos(), true);
    }

    #[Groups(['message:read'])]
    public function getAttachmentUrls(): array
    {
        if ($this->id === null) {
            return [];
        }

        return array_map(
            fn(string $filename) => sprintf('/api/messages/%d/attachments/%s', $this->id, rawurlencode($filename)),
            $this->getPhotos(),
        );
    }

    public function isBroadcast(): bool
    {
        return $this->isBroadcast;
    }

    public function setIsBroadcast(bool $isBroadcast): self
    {
        $this->isBroadcast = $isBroadcast;
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
