<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;

/**
 * Task
 *
 * @ORM\EntityListeners ({ControleOnline\Listener\LogListener::class})
 * @ORM\Table (name="tasks")
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\TaskRepository")
 */
#[ApiResource(
    operations: [
        new Get(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Patch(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ), 
        new Delete(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new Post(
            security: 'is_granted(\'ROLE_CLIENT\')',
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_CLIENT\')',
            uriTemplate: '/tasks',
        )
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    security: 'is_granted(\'ROLE_CLIENT\')',

    normalizationContext: ['groups' => ['task:read']],
    denormalizationContext: ['groups' => ['task:write']]
)]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['dueDate' => 'ASC', 'alterDate' => 'DESC'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: [
    'provider' => 'exact',
    'taskFor' => 'exact',
    'registeredBy' => 'exact',
    'taskStatus' => 'exact',
    'reason' => 'exact',
    'criticality' => 'exact',
    'category' => 'exact',
    'client' => 'exact',
    'order' => 'exact',
    'type' => 'exact',
])]


class Task
{
    /**
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"task:write","task:read","order:read"})
     */
    private $id;
    /**
     *
     * @ORM\Column(name="name", type="string", length=50, nullable=false)
     * @Groups({"task:write","task:read","order:read"})
     */
    private $name;
    /**
     *
     * @ORM\Column(name="task_type", type="string", length=50, nullable=false)
     * @Groups({"task:write","task:read","order:read"})
     */
    private $type;
    /**
     * @var \DateTimeInterface
     * @ORM\Column(name="due_date", type="datetime",  nullable=true, columnDefinition="DATETIME")
     * @Groups({"task:write","task:read","order:read"})
     */
    private $dueDate;
    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="registered_by_id", referencedColumnName="id", nullable=true)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $registeredBy;
    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="task_for_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $taskFor;
    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $client;
    /**
     * @var \ControleOnline\Entity\Status
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Status")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="task_status_id", referencedColumnName="id", nullable=true)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $taskStatus;
    /**
     * @var \ControleOnline\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="category_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $category;
    /**
     * @var \ControleOnline\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="reason_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $reason;
    /**
     * @var \ControleOnline\Entity\Category
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Category")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="criticality_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $criticality;
    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="provider_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"task:write","task:read","order:read"})
     */
    private $provider;
    /**
     * @var \ControleOnline\Entity\Order
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Order", inversedBy="task")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="order_id", referencedColumnName="id", nullable=true)
     * })
     * @Groups({"task:read"})
     */
    private $order;
    /**
     * @var \DateTimeInterface
     * @ORM\Column(name="created_at", type="datetime",  nullable=false, columnDefinition="DATETIME")
     * @Groups({"task:write","task:read","order:read"})
     */
    private $createdAt;
    /**
     * @var \DateTimeInterface
     * @ORM\Column(name="alter_date", type="datetime",  nullable=false, columnDefinition="DATETIME")
     * @Groups({"task:write","task:read","order:read"})
     */
    private $alterDate;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime('now');
        $this->alterDate = new \DateTime('now');
    }
    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * Get the value of name
     */
    public function getName()
    {
        return $this->name;
    }
    /**
     * Set the value of name
     */
    public function setName($name): self
    {
        $this->name = $name;
        return $this;
    }
    /**
     * Get the value of dueDate
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }
    /**
     * Set the value of dueDate
     */
    public function setDueDate($dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }
    /**
     * Get the value of registeredBy
     */
    public function getRegisteredBy()
    {
        return $this->registeredBy;
    }
    /**
     * Set the value of registeredBy
     */
    public function setRegisteredBy($registeredBy): self
    {
        $this->registeredBy = $registeredBy;
        return $this;
    }
    /**
     * Get the value of taskFor
     */
    public function getTaskFor()
    {
        return $this->taskFor;
    }
    /**
     * Set the value of taskFor
     */
    public function setTaskFor($taskFor): self
    {
        $this->taskFor = $taskFor;
        return $this;
    }
    /**
     * Get the value of taskStatus
     */
    public function getTaskStatus()
    {
        return $this->taskStatus;
    }
    /**
     * Set the value of taskStatus
     */
    public function setTaskStatus($taskStatus): self
    {
        $this->taskStatus = $taskStatus;
        return $this;
    }
    /**
     * Get the value of Category
     */
    public function getCategory()
    {
        return $this->category;
    }
    /**
     * Set the value of Category
     */
    public function setCategory($category): self
    {
        $this->category = $category;
        return $this;
    }
    /**
     * Get the value of reason
     */
    public function getReason()
    {
        return $this->reason;
    }
    /**
     * Set the value of reason
     */
    public function setReason($reason): self
    {
        $this->reason = $reason;
        return $this;
    }
    /**
     * Get the value of criticality
     */
    public function getCriticality()
    {
        return $this->criticality;
    }
    /**
     * Set the value of criticality
     */
    public function setCriticality($criticality): self
    {
        $this->criticality = $criticality;
        return $this;
    }
    /**
     * Get the value of provider
     */
    public function getProvider()
    {
        return $this->provider;
    }
    /**
     * Set the value of provider
     */
    public function setProvider($provider): self
    {
        $this->provider = $provider;
        return $this;
    }
    /**
     * Get the value of client
     */
    public function getClient()
    {
        return $this->client;
    }
    /**
     * Set the value of client
     */
    public function setClient($client): self
    {
        $this->client = $client;
        return $this;
    }
    /**
     * Get the value of order
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Set the value of order
     */
    public function setOrder($order): self
    {
        $this->order = $order;
        return $this;
    }
    /**
     * Get the value of createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    /**
     * Get the value of alterDate
     */
    public function getAlterDate()
    {
        return $this->alterDate;
    }
    /**
     * Get the value of type
     */
    public function getType()
    {
        return $this->type;
    }
    /**
     * Set the value of type
     */
    public function setType($type): self
    {
        $this->type = $type;
        return $this;
    }
}
