<?php

namespace ControleOnline\Entity; 
use ControleOnline\Listener\LogListener;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use stdClass;

/**
 * TaskInteration
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(
            security: 'is_granted(\'ROLE_ADMIN\') or (is_granted(\'ROLE_CLIENT\'))',
        ),
        new GetCollection(security: 'is_granted(\'ROLE_CLIENT\')'),

    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    security: 'is_granted(\'ROLE_CLIENT\')',
    normalizationContext: ['groups' => ['task_interaction:read']],
    denormalizationContext: ['groups' => ['task_interaction:write']]
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['task' => 'exact', 'task.id' => 'exact', 'task.taskFor' => 'exact', 'registeredBy' => 'exact', 'type' => 'exact', 'visibility' => 'exact', 'read' => 'exact'])]
#[ORM\Table(name: 'task_interations')]
#[ORM\EntityListeners([LogListener::class])]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\TaskInterationRepository::class)]
class TaskInteration
{
    /**
     *
     * @Groups({"task_interaction:read"})
     */
    #[ORM\Column(type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;
    /**
     *
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private $type;
    /**
     *
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\Column(name: 'visibility', type: 'string', length: 50, nullable: false)]
    private $visibility;
    /**
     *
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private $body;
    /**
     * @var \ControleOnline\Entity\People
     *
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\JoinColumn(name: 'registered_by_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\People::class)]
    private $registeredBy;
    /**
     * @var \ControleOnline\Entity\Task
     *
     *
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\Task::class)]
    private $task;
    /**
     * @var \ControleOnline\Entity\File
     *
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\File::class)]
    private $file;
    /**
     * @var \DateTimeInterface
     * @Groups({"task_interaction:read", "task_interaction:write"})
     */
    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false, columnDefinition: 'DATETIME')]
    private $createdAt;
    /**
     * @Groups({"task_interaction:read", "task_interaction:write","task_interaction:write"})
     */
    #[ORM\Column(name: '`read`', type: 'integer', nullable: false)]
    private $read;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime('now');
        $this->visibility = 'private';
        $this->read = false;
    }
    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
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
    /**
     * Get body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }
    /**
     * Set body
     *
     * @param string $body
     * @return TaskInteration
     */
    public function setBody($body)
    {
        $this->body = $body;
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
     * Get the value of task
     */
    public function getTask()
    {
        return $this->task;
    }
    /**
     * Set the value of task
     */
    public function setTask($task): self
    {
        $this->task = $task;
        return $this;
    }
    /**
     * Get the value of file
     */
    public function getFile()
    {
        return $this->file;
    }
    /**
     * Set the value of file
     */
    public function setFile($file): self
    {
        $this->file = $file;
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
     * Get the value of visibility
     */
    public function getVisibility()
    {
        return $this->visibility;
    }
    /**
     * Set the value of visibility
     */
    public function setVisibility($visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }
    /**
     * Get the value of read
     */
    public function getRead()
    {
        return $this->read;
    }
    /**
     * Set the value of read
     */
    public function setRead($read)
    {
        $this->read = $read;
        return $this;
    }
}
