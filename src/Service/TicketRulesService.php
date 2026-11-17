<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rules shared by the request (ticket) screens: relationship (CRM) and support.
 *
 * - Responders of a context: who may be the responsible person and answer on the company side.
 *   relationship: the company's salesmen (people_link salesman) + configured sales assistants.
 *   support: the people configured in the Manager (support-responders).
 * - Distribution: tickets are born without a responsible person; this engine picks one.
 * - The customer side (task client, its employees, the author) can always answer its own ticket.
 *
 * Settings are per company (multi-tenant): config rows of the ticket provider.
 */
class TicketRulesService
{
    public const RELATIONSHIP = 'relationship';
    public const SUPPORT = 'support';
    public const TYPES = [self::RELATIONSHIP, self::SUPPORT];

    public const SUPPORT_RESPONDERS = 'support-responders';
    public const SUPPORT_STRATEGY = 'support-distribution-strategy';
    public const CRM_ASSISTANTS = 'crm-assistants';
    /** Existing CRM setting (SalesmanDistributionService). */
    public const CRM_STRATEGY = 'salesman-distribution-strategy';

    public const STRATEGIES = ['random', 'least_open'];

    public function __construct(
        private EntityManagerInterface $manager,
        private ConfigService $configService,
    ) {}

    public static function isTicket(?Task $task): bool
    {
        return $task instanceof Task && in_array($task->getType(), self::TYPES, true);
    }

    /** @return list<People> */
    public function responders(People $company, string $type): array
    {
        $ids = $this->configuredIds($company, $type === self::SUPPORT ? self::SUPPORT_RESPONDERS : self::CRM_ASSISTANTS);
        if ($type === self::RELATIONSHIP) {
            foreach ($this->manager->getRepository(PeopleLink::class)->findBy(['company' => $company, 'linkType' => 'salesman']) as $link) {
                $id = $link->getPeople()?->getId();
                if ($id) {
                    $ids[] = (int) $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        return $this->manager->getRepository(People::class)->findBy(['id' => $ids], ['name' => 'ASC']);
    }

    public function isResponder(People $company, string $type, ?People $people): bool
    {
        if (!$people instanceof People) {
            return false;
        }
        foreach ($this->responders($company, $type) as $responder) {
            if ($responder->getId() === $people->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Who may write in the ticket: the customer side, the company itself (its AI), or a responder
     * of the ticket's context.
     */
    public function canReply(Task $task, People $author): bool
    {
        $provider = $task->getProvider();
        if (!$provider instanceof People || $author->getId() === $provider->getId()) {
            return true;
        }
        if ($this->isCustomerSide($task, $author)) {
            return true;
        }

        return $this->isResponder($provider, (string) $task->getType(), $author);
    }

    public function isCustomerSide(Task $task, People $people): bool
    {
        $client = $task->getClient();
        if ($task->getRegisteredBy()?->getId() === $people->getId()) {
            // The author of the request, unless the author works for the provider (opened it internally).
            return !$this->worksFor($people, $task->getProvider());
        }
        if (!$client instanceof People) {
            return false;
        }

        return $client->getId() === $people->getId() || $this->worksFor($people, $client);
    }

    /** Picks the responsible person for a ticket without one (null when nobody is configured). */
    public function pickResponsible(People $company, string $type): ?People
    {
        $responders = $this->responders($company, $type);
        if ($responders === []) {
            return null;
        }

        return $this->strategy($company, $type) === 'least_open'
            ? $this->leastOpen($company, $type, $responders)
            : $responders[array_rand($responders)];
    }

    public function strategy(People $company, string $type): string
    {
        $raw = (string) $this->configService->getConfig($company, $type === self::SUPPORT ? self::SUPPORT_STRATEGY : self::CRM_STRATEGY);

        return in_array($raw, ['least_open', 'least_clients'], true) ? 'least_open' : 'random';
    }

    /** @return array{support_strategy: string, support_responders: list<int>, crm_strategy: string, crm_assistants: list<int>} */
    public function settings(People $company): array
    {
        return [
            'support_strategy' => $this->strategy($company, self::SUPPORT),
            'support_responders' => $this->configuredIds($company, self::SUPPORT_RESPONDERS),
            'crm_strategy' => $this->strategy($company, self::RELATIONSHIP),
            'crm_assistants' => $this->configuredIds($company, self::CRM_ASSISTANTS),
        ];
    }

    /** @param array<string, mixed> $input */
    public function saveSettings(People $company, array $input): array
    {
        $module = $this->configService->discoveryModule('ui-crm');
        if (isset($input['support_strategy'])) {
            $this->configService->addConfig($company, self::SUPPORT_STRATEGY, $this->validStrategy($input['support_strategy']), $module);
        }
        if (isset($input['crm_strategy'])) {
            // Same key the CRM engine already reads; least_open is stored as its least_clients.
            $value = $this->validStrategy($input['crm_strategy']) === 'least_open' ? 'least_clients' : 'random';
            $this->configService->addConfig($company, self::CRM_STRATEGY, $value, $module);
        }
        foreach (['support_responders' => self::SUPPORT_RESPONDERS, 'crm_assistants' => self::CRM_ASSISTANTS] as $field => $key) {
            if (array_key_exists($field, $input)) {
                $ids = $this->employeeIds($company, is_array($input[$field]) ? $input[$field] : []);
                $this->configService->addConfig($company, $key, (string) json_encode($ids), $module);
            }
        }

        return $this->settings($company);
    }

    /** @return list<int> */
    private function configuredIds(People $company, string $key): array
    {
        $value = $this->configService->getConfig($company, $key, true);

        return is_array($value) ? array_values(array_filter(array_map('intval', $value))) : [];
    }

    private function validStrategy(mixed $value): string
    {
        return in_array($value, self::STRATEGIES, true) ? (string) $value : 'random';
    }

    /**
     * Only people who actually work for the company can be configured (no ids from other tenants).
     *
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function employeeIds(People $company, array $ids): array
    {
        $out = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $people = $id > 0 ? $this->manager->getRepository(People::class)->find($id) : null;
            if ($people instanceof People && $this->worksFor($people, $company)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    private function worksFor(People $people, ?People $company): bool
    {
        if (!$company instanceof People) {
            return false;
        }

        return (bool) $this->manager->getRepository(PeopleLink::class)->findOneBy([
            'people' => $people,
            'company' => $company,
            'linkType' => PeopleLink::EMPLOYEE_LINK,
        ]);
    }

    /** @param list<People> $responders */
    private function leastOpen(People $company, string $type, array $responders): People
    {
        $rows = $this->manager->createQueryBuilder()
            ->select('IDENTITY(t.taskFor) AS responder, COUNT(t.id) AS total')
            ->from(Task::class, 't')
            ->join('t.taskStatus', 's')
            ->where('t.provider = :company')
            ->andWhere('t.type = :type')
            ->andWhere('s.realStatus <> :closed')
            ->andWhere('t.taskFor IN (:responders)')
            ->groupBy('t.taskFor')
            ->setParameter('company', $company)
            ->setParameter('type', $type)
            ->setParameter('closed', 'closed')
            ->setParameter('responders', $responders)
            ->getQuery()
            ->getArrayResult();
        $open = [];
        foreach ($rows as $row) {
            $open[(int) $row['responder']] = (int) $row['total'];
        }
        usort($responders, static fn (People $a, People $b) => ($open[$a->getId()] ?? 0) <=> ($open[$b->getId()] ?? 0));

        return $responders[0];
    }
}
