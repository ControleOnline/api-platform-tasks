<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rules shared by the request (ticket) screens: relationship (CRM) and support.
 *
 * - relationship follows the CRM's own client distribution (no second setting): the request goes to the
 *   salesman already linked to the client (people_link sellers-client/client); a client without one gets
 *   a salesman from the CRM strategy (SalesmanDistributionService, salesman-distribution-strategy) and the
 *   link is recorded so the next requests stay with the same salesman. Responders: the company salesmen.
 * - support has its own rule, set in the Manager: the people who answer (support-responders) and the
 *   strategy (support-distribution-strategy: random | least_open).
 * - The customer side (task client, its employees, the author) and the request's responsible person can
 *   always answer; on the company side only the context's responders (or the company itself, i.e. its AI).
 *
 * Settings are per company (multi-tenant): config rows of the request provider.
 */
class TicketRulesService
{
    public const RELATIONSHIP = 'relationship';
    public const SUPPORT = 'support';
    public const TYPES = [self::RELATIONSHIP, self::SUPPORT];

    public const SUPPORT_RESPONDERS = 'support-responders';
    public const SUPPORT_STRATEGY = 'support-distribution-strategy';
    public const STRATEGIES = ['random', 'least_open'];

    private const CLIENT_LINKS = ['sellers-client', 'client'];

    public function __construct(
        private EntityManagerInterface $manager,
        private ConfigService $configService,
        private ?SalesmanDistributionService $salesmanDistribution = null,
        private ?TicketAttendanceService $attendance = null,
    ) {}

    public static function isTicket(?Task $task): bool
    {
        return $task instanceof Task && in_array($task->getType(), self::TYPES, true);
    }

    /** @return list<People> */
    public function responders(People $company, string $type): array
    {
        if ($type === self::RELATIONSHIP) {
            $ids = [];
            foreach ($this->manager->getRepository(PeopleLink::class)->findBy(['company' => $company, 'linkType' => 'salesman']) as $link) {
                if ($link->getPeople()?->getId()) {
                    $ids[] = (int) $link->getPeople()->getId();
                }
            }
        } else {
            $ids = $this->configuredIds($company, self::SUPPORT_RESPONDERS);
        }
        $ids = array_values(array_unique($ids));

        return $ids === [] ? [] : $this->manager->getRepository(People::class)->findBy(['id' => $ids], ['name' => 'ASC']);
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

    /** Who may write in the request (see class doc). */
    public function canReply(Task $task, People $author): bool
    {
        $provider = $task->getProvider();
        if (!$provider instanceof People || $author->getId() === $provider->getId()) {
            return true;
        }
        if ($task->getTaskFor()?->getId() === $author->getId() || $this->isCustomerSide($task, $author)) {
            return true;
        }
        if ($this->attendance?->isAiPeople($provider, $author)) {
            return true;
        }

        return $this->isResponder($provider, (string) $task->getType(), $author);
    }

    public function isCustomerSide(Task $task, People $people): bool
    {
        $client = $task->getClient();
        // The request's client is always the customer side, even when that person also works for the
        // provider (e.g. a platform employee opening a request in the requests screen).
        if ($client instanceof People && $client->getId() === $people->getId()) {
            return true;
        }
        if ($task->getRegisteredBy()?->getId() === $people->getId()) {
            // The author of the request, unless the author works for the provider (opened it internally).
            return !$this->worksFor($people, $task->getProvider());
        }
        if (!$client instanceof People) {
            return false;
        }

        return $this->worksFor($people, $client);
    }

    /** Responsible person for a request born without one (null when nobody can take it). */
    public function pickResponsible(Task $task): ?People
    {
        $company = $task->getProvider();
        if (!$company instanceof People) {
            return null;
        }
        if ($task->getType() === self::RELATIONSHIP) {
            return $this->clientSalesman($company, $task->getClient());
        }
        $responders = $this->responders($company, self::SUPPORT);
        if ($responders === []) {
            return null;
        }

        return $this->supportStrategy($company) === 'least_open'
            ? $this->leastOpen($company, self::SUPPORT, $responders)
            : $responders[array_rand($responders)];
    }

    /**
     * CRM rule: the salesman linked to the client (or to the company the client person works for);
     * otherwise the CRM strategy picks one and the link is recorded.
     */
    public function clientSalesman(People $company, ?People $client): ?People
    {
        $parties = $this->clientParties($client);
        if ($parties !== []) {
            $row = $this->manager->createQueryBuilder()
                ->select('IDENTITY(clientLink.company) AS salesman')
                ->from(PeopleLink::class, 'clientLink')
                ->join(PeopleLink::class, 'salesmanLink', 'WITH', 'salesmanLink.people = clientLink.company AND salesmanLink.linkType = :salesman')
                ->where('clientLink.people IN (:parties)')
                ->andWhere('clientLink.linkType IN (:clientLinks)')
                ->andWhere('salesmanLink.company = :company')
                ->setParameter('parties', $parties)
                ->setParameter('clientLinks', self::CLIENT_LINKS)
                ->setParameter('salesman', 'salesman')
                ->setParameter('company', $company)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if (!empty($row['salesman'])) {
                return $this->manager->getRepository(People::class)->find((int) $row['salesman']);
            }
        }

        $salesman = $this->salesmanDistribution?->discoverSalesman($company);
        if ($salesman instanceof People && $parties !== []) {
            // Same link the CRM records (SalesmanService::discoverSalesmanForClient). Only persisted here:
            // this runs in Task prePersist (at persist(), before the flush that saves both).
            $link = new PeopleLink();
            $link->setCompany($salesman);
            $link->setPeople($parties[0]);
            $link->setLinkType('sellers-client');
            $this->manager->persist($link);
        }

        return $salesman instanceof People ? $salesman : null;
    }

    public function supportStrategy(People $company): string
    {
        return (string) $this->configService->getConfig($company, self::SUPPORT_STRATEGY) === 'least_open' ? 'least_open' : 'random';
    }

    /** @return array{support_strategy: string, support_responders: list<int>, attendance: array} */
    public function settings(People $company): array
    {
        return [
            'support_strategy' => $this->supportStrategy($company),
            'support_responders' => $this->configuredIds($company, self::SUPPORT_RESPONDERS),
            // Per kind of request: AI or human first, welcome message and the AI person's name.
            'attendance' => $this->attendance?->settings($company) ?? [],
        ];
    }

    /** @param array<string, mixed> $input */
    public function saveSettings(People $company, array $input): array
    {
        $module = $this->configService->discoveryModule('ui-crm');
        if (isset($input['support_strategy'])) {
            $strategy = in_array($input['support_strategy'], self::STRATEGIES, true) ? (string) $input['support_strategy'] : 'random';
            $this->configService->addConfig($company, self::SUPPORT_STRATEGY, $strategy, $module);
        }
        if (array_key_exists('support_responders', $input)) {
            $ids = $this->employeeIds($company, is_array($input['support_responders']) ? $input['support_responders'] : []);
            $this->configService->addConfig($company, self::SUPPORT_RESPONDERS, (string) json_encode($ids), $module);
        }
        if (isset($input['attendance']) && is_array($input['attendance'])) {
            $this->attendance?->save($company, $input['attendance']);
        }

        return $this->settings($company);
    }

    /**
     * The client as the CRM sees it: the client itself first, then the companies a client person works
     * for (a request opened by a customer company's employee belongs to that company's salesman).
     *
     * @return list<People>
     */
    private function clientParties(?People $client): array
    {
        if (!$client instanceof People) {
            return [];
        }
        $parties = [$client];
        foreach ($this->manager->getRepository(PeopleLink::class)->findBy(['people' => $client, 'linkType' => PeopleLink::EMPLOYEE_LINK]) as $link) {
            if ($link->getCompany() instanceof People) {
                $parties[] = $link->getCompany();
            }
        }

        return $parties;
    }

    /** @return list<int> */
    private function configuredIds(People $company, string $key): array
    {
        $value = $this->configService->getConfig($company, $key, true);

        return is_array($value) ? array_values(array_filter(array_map('intval', $value))) : [];
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
