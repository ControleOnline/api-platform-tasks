<?php

namespace ControleOnline\Service;

use ControleOnline\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class OverdueOpportunityMaintenanceService
{
    private const OPPORTUNITY_CONTEXT = 'relationship';

    public function __construct(
        private TaskRepository $taskRepository,
        private StatusService $statusService,
        private EntityManagerInterface $manager,
    ) {}

    public function openPendingOpportunities(
        ?\DateTimeImmutable $referenceTime = null,
    ): array {
        $now = $referenceTime ?? new \DateTimeImmutable('now');
        $pendingStatus = $this->statusService->discoveryStatus(
            'pending',
            'pending',
            self::OPPORTUNITY_CONTEXT
        );
        $openStatus = $this->statusService->discoveryStatus(
            'open',
            'open',
            self::OPPORTUNITY_CONTEXT
        );

        $tasks = $this->taskRepository->findOverduePendingTasksByTypeAndStatus(
            self::OPPORTUNITY_CONTEXT,
            $pendingStatus,
            $now,
        );

        $updatedIds = [];

        foreach ($tasks as $task) {
            $task->setTaskStatus($openStatus);
            $task->setAlterDate(\DateTimeImmutable::createFromMutable(
                \DateTime::createFromInterface($now)
            ));
            $updatedIds[] = (int) $task->getId();
        }

        if ($updatedIds !== []) {
            $this->manager->flush();
        }

        return [
            'referenceTime' => $now->format(\DateTimeInterface::ATOM),
            'updatedTotal' => count($updatedIds),
            'taskIds' => $updatedIds,
        ];
    }
}
