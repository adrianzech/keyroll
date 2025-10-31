<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\HostConnectionStatus;
use App\Repository\HostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HostRepository::class)]
#[ORM\Table(name: '`host`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'entity.host.validation.name.duplicate')]
class Host
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    #[Assert\NotBlank(message: 'entity.host.validation.name.required')]
    private ?string $name = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'entity.host.validation.hostname.required')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9.-]+$/', message: 'entity.host.validation.hostname.invalid_format')]
    private ?string $hostname = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'entity.host.validation.port.required')]
    #[Assert\Range(min: 1, max: 65535, notInRangeMessage: 'entity.host.validation.port.invalid_range')]
    private int $port = 22;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'entity.host.validation.username.required')]
    private string $username = '';

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'hosts')]
    #[ORM\JoinTable(name: 'host_category')]
    private Collection $categories;

    #[ORM\Column(type: 'string', nullable: true, enumType: HostConnectionStatus::class)]
    private ?HostConnectionStatus $connectionStatus = HostConnectionStatus::UNKNOWN;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->connectionStatus = HostConnectionStatus::UNKNOWN;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        // Always update 'updatedAt' on both create and update
        $this->updatedAt = new \DateTimeImmutable();

        // Set 'createdAt' only if it's currently null (i.e., during PrePersist)
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function setHostname(string $hostname): static
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getConnectionStatus(): ?HostConnectionStatus
    {
        return $this->connectionStatus;
    }

    public function setConnectionStatus(?HostConnectionStatus $connectionStatus): static
    {
        $this->connectionStatus = $connectionStatus;

        return $this;
    }
}
