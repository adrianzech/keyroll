<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: '`category`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'entity.category.validation.name.duplicate')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'entity.category.validation.name.required')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Host>
     */
    #[ORM\ManyToMany(targetEntity: Host::class, mappedBy: 'categories')]
    private Collection $hosts;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'categories')]
    private Collection $users;

    public function __construct()
    {
        $this->hosts = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Host>
     */
    public function getHosts(): Collection
    {
        return $this->hosts;
    }

    public function addHost(Host $host): static
    {
        if (!$this->hosts->contains($host)) {
            $this->hosts->add($host);
            // Ensure the owning side is updated
            $host->addCategory($this);
        }

        return $this;
    }

    public function removeHost(Host $host): static
    {
        if ($this->hosts->removeElement($host)) {
            // Ensure the owning side is updated
            $host->removeCategory($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            // Ensure the owning side is updated
            $user->addCategory($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // Ensure the owning side is updated
            $user->removeCategory($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
