<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Config;
use ControleOnline\Entity\People;
use ControleOnline\Entity\PeopleLink;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * How each kind of request (support, relationship) is attended, per company (config ticket-attendance):
 * - first: "ai" (default) — the company AI attends first (task.aiAgent, no responsible person) and hands
 *   off to a person when needed; "human" — straight to the responsible rule (TicketRulesService);
 * - welcome: message sent in the AI's name when the request starts;
 * - ai_people: the AI is a person of the company (employee link + bot user, like SkyNetService's bots),
 *   so its messages have an author with a name (renamable, e.g. "Tião IA") and a photo.
 */
class TicketAttendanceService
{
    public const CONFIG = 'ticket-attendance';
    public const AI_PROVIDERS = 'ai-providers';
    private const DEFAULT_NAMES = [TicketRulesService::SUPPORT => 'Assistente de Suporte IA', TicketRulesService::RELATIONSHIP => 'Assistente Comercial IA'];

    public function __construct(
        private EntityManagerInterface $manager,
        private ConfigService $configService,
    ) {}

    public function first(People $company, string $type): string
    {
        return ($this->all($company)[$type]['first'] ?? 'ai') === 'human' ? 'human' : 'ai';
    }

    public function welcome(People $company, string $type): ?string
    {
        $text = trim((string) ($this->all($company)[$type]['welcome'] ?? ''));

        return $text !== '' ? $text : null;
    }

    /** True when the AI attends this request first (needs an AI configured for the company). */
    public function aiFirst(Task $task): bool
    {
        $company = $task->getProvider();

        return $company instanceof People && $this->first($company, (string) $task->getType()) === 'ai' && $this->companyHasAi($company);
    }

    /** The AI person of this kind of request (created on first use). */
    public function aiPeople(People $company, string $type): People
    {
        $id = (int) ($this->all($company)[$type]['ai_people'] ?? 0);
        $people = $id ? $this->manager->getRepository(People::class)->find($id) : null;
        if ($people instanceof People) {
            return $people;
        }

        $people = new People();
        $people->setName(self::DEFAULT_NAMES[$type] ?? 'Assistente IA');
        $people->setPeopleType('F');
        $this->manager->persist($people);
        $link = new PeopleLink();
        $link->setCompany($company);
        $link->setPeople($people);
        $link->setLinkType(PeopleLink::EMPLOYEE_LINK);
        $this->manager->persist($link);
        $user = new User();
        $user->setUsername(mb_substr(sprintf('ia-%s-%d', $type, $company->getId()), 0, 50));
        $user->setHash(bin2hex(random_bytes(24)));
        $user->setPeople($people);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->store($company, [$type => ['ai_people' => $people->getId()]]);

        return $people;
    }

    public function isAiPeople(People $company, ?People $people): bool
    {
        if (!$people instanceof People) {
            return false;
        }
        foreach ($this->all($company) as $settings) {
            if ((int) ($settings['ai_people'] ?? 0) === (int) $people->getId()) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array{first: string, welcome: ?string, ai_name: string, ai_people: int}> */
    public function settings(People $company): array
    {
        $out = [];
        foreach (TicketRulesService::TYPES as $type) {
            $out[$type] = [
                'first' => $this->first($company, $type),
                'welcome' => $this->welcome($company, $type),
                'ai_name' => $this->aiPeople($company, $type)->getName(),
                'ai_people' => $this->aiPeople($company, $type)->getId(),
            ];
        }

        return $out;
    }

    /** @param array<string, array{first?: string, welcome?: ?string, ai_name?: string}> $input */
    public function save(People $company, array $input): array
    {
        $changes = [];
        foreach (TicketRulesService::TYPES as $type) {
            $row = (array) ($input[$type] ?? []);
            if (array_key_exists('first', $row)) {
                $changes[$type]['first'] = $row['first'] === 'human' ? 'human' : 'ai';
            }
            if (array_key_exists('welcome', $row)) {
                $changes[$type]['welcome'] = mb_substr(trim((string) $row['welcome']), 0, 2000);
            }
            $name = trim((string) ($row['ai_name'] ?? ''));
            if ($name !== '') {
                $this->aiPeople($company, $type)->setName(mb_substr($name, 0, 60));
                $this->manager->flush();
            }
        }
        $this->store($company, $changes);

        return $this->settings($company);
    }

    /** An enabled provider with an API key in the company's ai-providers config. */
    public function companyHasAi(People $company): bool
    {
        $row = $this->manager->getRepository(Config::class)->findOneBy(['people' => $company, 'configKey' => self::AI_PROVIDERS]);
        $providers = $row ? json_decode((string) $row->getConfigValue(), true) : null;
        foreach (is_array($providers) ? $providers : [] as $config) {
            if (is_array($config) && !empty($config['enabled']) && !empty($config['api_key'])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, mixed>> */
    private function all(People $company): array
    {
        $value = $this->configService->getConfig($company, self::CONFIG, true);

        return is_array($value) ? $value : [];
    }

    private function store(People $company, array $changes): void
    {
        $all = $this->all($company);
        foreach ($changes as $type => $values) {
            $all[$type] = array_merge((array) ($all[$type] ?? []), $values);
        }
        $this->configService->addConfig($company, self::CONFIG, (string) json_encode($all, JSON_UNESCAPED_UNICODE), $this->configService->discoveryModule('ui-crm'));
    }
}
