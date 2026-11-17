<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\TicketRulesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Requests (relationship/support) helpers for the CRM, Support and Manager screens.
 * Everything is scoped to a company the logged user works for (multi-tenant).
 */
#[Route('/tickets')]
#[IsGranted('ROLE_HUMAN')]
class TicketController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private PeopleService $peopleService,
        private TicketRulesService $ticketRules,
    ) {}

    /** People who may be responsible/answer requests of a context in the company. */
    #[Route('/responders', methods: ['GET'])]
    public function responders(Request $request): JsonResponse
    {
        $company = $this->company($request->query->get('company_id'));
        $type = (string) $request->query->get('type', TicketRulesService::SUPPORT);
        if (!in_array($type, TicketRulesService::TYPES, true)) {
            return new JsonResponse(['error' => 'type must be relationship or support'], 400);
        }
        $me = $this->peopleService->getMyPeople();

        return new JsonResponse([
            'type' => $type,
            'can_reply' => $this->ticketRules->isResponder($company, $type, $me),
            'responders' => array_map(static fn (People $p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'alias' => $p->getAlias(),
            ], $this->ticketRules->responders($company, $type)),
        ]);
    }

    #[Route('/settings', methods: ['GET'])]
    public function settings(Request $request): JsonResponse
    {
        $company = $this->company($request->query->get('company_id'));

        return new JsonResponse($this->ticketRules->settings($company) + ['employees' => $this->employees($company)]);
    }

    #[Route('/settings', methods: ['PUT', 'POST'])]
    public function saveSettings(Request $request): JsonResponse
    {
        $input = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($input)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }
        $company = $this->company($input['company_id'] ?? $request->query->get('company_id'), PeopleLink::ADMIN_LINK);

        return new JsonResponse($this->ticketRules->saveSettings($company, $input) + ['employees' => $this->employees($company)]);
    }

    /** @param list<string>|null $linkTypes */
    private function company(mixed $companyId, ?array $linkTypes = null): People
    {
        $company = is_numeric($companyId) ? $this->manager->getRepository(People::class)->find((int) $companyId) : null;
        if (!$company instanceof People || !$this->peopleService->canAccessCompany($company, null, $linkTypes)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Empresa não encontrada.');
        }

        return $company;
    }

    /** @return list<array{id: int, name: string, alias: ?string}> */
    private function employees(People $company): array
    {
        $out = [];
        foreach ($this->manager->getRepository(PeopleLink::class)->findBy(['company' => $company, 'linkType' => PeopleLink::EMPLOYEE_LINK]) as $link) {
            $p = $link->getPeople();
            if ($p instanceof People && !isset($out[$p->getId()])) {
                $out[$p->getId()] = ['id' => $p->getId(), 'name' => $p->getName(), 'alias' => $p->getAlias()];
            }
        }
        usort($out, static fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

        return array_values($out);
    }
}
