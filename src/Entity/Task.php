<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Entity\Category;
use ControleOnline\Entity\Order;
use ControleOnline\Repository\TaskRepository;

use DateTime;
use DateTimeInterface;
use stdClass;

#[ORM\Table(name: 'tasks')]

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => 'text/csv'],
    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']],
    security: "is_granted('ROLE_CLIENT')",
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_CLIENT')",
            uriTemplate: '/tasks'
        ),
        new Get(security: "is_granted('ROLE_CLIENT')"),
        new Post(security: "is_granted('ROLE_CLIENT')"),
        new Put(security: "is_granted('ROLE_CLIENT')"),
        new Patch(security: "is_granted('ROLE_CLIENT')"),
        new Delete(security: "is_granted('ROLE_CLIENT')")
    ]
)]
#[ApiFilter(OrderFilter::class, properties: ['dueDate' => 'ASC', 'alterDate' => 'DESC'])]
#[ApiFilter(SearchFilter::class, properties: [
    'provider' => 'exact',
    'taskFor' => 'exact',
    'registeredBy' => 'exact',
    'taskStatus' => 'exact',
    'reason' => 'exact',
    'criticality' => 'exact',
    'category' => 'exact',
    'client' => 'exact',
    'order' => 'exact',
    'type' => 'exact'
])]
class Task
{
    #[ORM\Column(type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $id;

    #[ORM\Column(name: 'name', type: 'string', length: 50, nullable: false)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $name;

    #[ORM\Column(name: 'task_type', type: 'string', length: 50, nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $type;

    #[ORM\Column(name: 'due_date', type: 'datetime', nullable: true, columnDefinition: 'DATETIME')]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $dueDate;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'registered_by_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $registeredBy;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'task_for_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $taskFor;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $client;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[ORM\JoinColumn(name: 'task_status_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $taskStatus;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $category;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'reason_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $reason;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'criticality_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $criticality;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $provider;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'task')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task:read'])]
    private $order;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false, columnDefinition: 'DATETIME')]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $createdAt;

    #[ORM\Column(name: 'alter_date', type: 'datetime', nullable: true, columnDefinition: 'DATETIME')]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private $alterDate;

    #[ORM\Column(name: 'announce', type: 'string', nullable: true)]
    #[Groups(['task:write', 'task:read', 'order:read'])]
    private ?string $announce = null;

    public function __construct()
    {
        $this->createdAt = new DateTime('now');
        $this->alterDate = new DateTime('now');
        $this->announce = json_encode(new stdClass());
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getDueDate()
    {
        return $this->dueDate;
    }

    public function setDueDate($dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getRegisteredBy()
    {
        return $this->registeredBy;
    }

    public function setRegisteredBy($registeredBy): self
    {
        $this->registeredBy = $registeredBy;
        return $this;
    }

    public function getTaskFor()
    {
        return $this->taskFor;
    }

    public function setTaskFor($taskFor): self
    {
        $this->taskFor = $taskFor;
        return $this;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function setClient($client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getTaskStatus()
    {
        return $this->taskStatus;
    }

    public function setTaskStatus($taskStatus): self
    {
        $this->taskStatus = $taskStatus;
        return $this;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getCriticality()
    {
        return $this->criticality;
    }

    public function setCriticality($criticality): self
    {
        $this->criticality = $criticality;
        return $this;
    }

    public function getProvider()
    {
        return $this->provider;
    }

    public function setProvider($provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getAlterDate()
    {
        return $this->alterDate;
    }

    public function getAnnounce(bool $decode = false): string|array
    {
        // Ensure we're decoding a string, even if it was temporarily an array internally
        $announceString = is_array($this->announce) ? json_encode($this->announce) : $this->announce;
        return $decode ? (json_decode((string) $announceString, true) ?: []) : (string) $announceString;
    }

    public function addAnnounce(mixed $value): self
    {
        $announce = $this->getAnnounce(true);

        if (!in_array($value, $announce))
            array_push($announce, $value);

        return $this->setAnnounce($announce);
    }

    public function setAnnounce(string|array|object $announce): self
    {
        if (is_string($announce))
            $announce = json_decode($announce, true);

        $this->announce = json_encode($announce);
        return $this;
    }
}
