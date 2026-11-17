<?php

namespace ControleOnline\EventSubscriber;

use ControleOnline\Entity\Task;
use ControleOnline\Event\EntityChangedEvent;
use ControleOnline\Service\TicketRulesService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * A request's responsible person must be one of the responders of its context
 * (relationship: salesmen/assistants; support: people configured in the Manager).
 * Only checked for changes made by a logged user; the distribution engine already picks from the list.
 */
class TicketResponsibleGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TicketRulesService $ticketRules,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [EntityChangedEvent::class => 'onEntityChanged'];
    }

    public function onEntityChanged(EntityChangedEvent $event): void
    {
        $task = $event->getEntity();
        if (!in_array($event->getPhase(), ['preUpdate', EntityChangedEvent::PRE_UPDATE], true) || !$task instanceof Task) {
            return;
        }
        if (!TicketRulesService::isTicket($task) || !is_object($this->tokenStorage->getToken()?->getUser())) {
            return;
        }
        $old = $event->getOldEntity();
        $newResponsible = $task->getTaskFor();
        $changed = !$old instanceof Task
            || $old->getTaskFor()?->getId() !== $newResponsible?->getId()
            || $old->getType() !== $task->getType();
        if (!$changed || $newResponsible === null || !$task->getProvider()) {
            return;
        }
        if (!$this->ticketRules->isResponder($task->getProvider(), (string) $task->getType(), $newResponsible)) {
            throw new AccessDeniedHttpException('Esta pessoa não atende chamados deste contexto.');
        }
    }
}
