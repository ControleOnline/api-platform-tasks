<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\TaskInteration;
use ControleOnline\Service\DomainService;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\StatusService;
use ControleOnline\Service\TicketRulesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Public entry point for visitors who are not registered (wp-mkt-community#2): the site's contact
 * form creates a relationship request of the company that owns the App-Domain. It is born without a
 * responsible person (CRM distribution picks one). The CRM reply goes out by the WhatsApp/e-mail the
 * visitor gave (announce), and new messages from that contact continue the same request.
 */
class PublicContactController
{
    private const MAX_PER_HOUR = 5;

    public function __construct(
        private EntityManagerInterface $manager,
        private DomainService $domainService,
        private PeopleService $peopleService,
        private StatusService $statusService,
        private CacheInterface $cache,
    ) {}

    #[Route('/public/contacts', name: 'controleonline_public_contacts', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $input = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($input)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }
        // Honeypot: bots fill every field.
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return new JsonResponse(['ok' => true], 202);
        }

        $name = mb_substr(trim(strip_tags((string) ($input['name'] ?? ''))), 0, 80);
        $message = mb_substr(trim(strip_tags((string) ($input['message'] ?? ''))), 0, 4000);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $phone = $this->phone((string) ($input['phone'] ?? ''));
        if (mb_strlen($message) < 3) {
            return new JsonResponse(['error' => 'Escreva sua mensagem.'], 400);
        }
        if ($email === '' && $phone === null) {
            return new JsonResponse(['error' => 'Informe um WhatsApp ou e-mail para a resposta.'], 400);
        }
        $visitor = (string) ($input['visitor_ip'] ?? $request->getClientIp());
        if (!$this->withinLimit($email ?: implode('', $phone ?? []), $visitor)) {
            return new JsonResponse(['error' => 'Muitas mensagens. Tente novamente mais tarde.'], 429);
        }

        $company = $this->domainService->getPeopleDomain()->getPeople();
        $client = $this->peopleService->discoveryPeople(null, $email ?: null, $phone ?? [], $name !== '' ? $name : null, 'F');
        $destinations = array_values(array_filter([$phone ? implode('', $phone) : null, $email ?: null]));

        $task = $this->openRequest($company, $client, $destinations, $name, $message);
        $interaction = new TaskInteration();
        $interaction->setTask($task);
        $interaction->setRegisteredBy($client);
        $interaction->setType('comment');
        $interaction->setVisibility('public');
        $interaction->setStatus('done');
        $interaction->setBody($this->withSource($message, $input));
        $interaction->setRead(0);
        $interaction->setNotified(0);
        $this->manager->persist($interaction);
        $task->setAlterDate(new \DateTime('now'));
        $this->manager->flush();

        return new JsonResponse(['ok' => true, 'request_id' => $task->getId()], 201);
    }

    /** The visitor's open request continues; a new one only when there is none. */
    private function openRequest(People $company, People $client, array $destinations, string $name, string $message): Task
    {
        $open = $this->manager->createQueryBuilder()
            ->select('t')->from(Task::class, 't')->join('t.taskStatus', 's')
            ->where('t.provider = :company')->andWhere('t.client = :client')->andWhere('t.type = :type')
            ->andWhere('s.realStatus <> :closed')
            ->setParameters(['company' => $company, 'client' => $client, 'type' => TicketRulesService::RELATIONSHIP, 'closed' => 'closed'])
            ->orderBy('t.id', 'DESC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
        if ($open instanceof Task) {
            foreach ($destinations as $destination) {
                if (!in_array($destination, $open->getAnnounce(true), true)) {
                    $open->addAnnounce($destination);
                }
            }

            return $open;
        }

        $task = new Task();
        $task->setType(TicketRulesService::RELATIONSHIP);
        $task->setName(mb_substr(($name !== '' ? $name . ': ' : 'Site: ') . $message, 0, 50));
        $task->setRegisteredBy($client);
        $task->setClient($client);
        $task->setProvider($company);
        $task->setTaskStatus($this->statusService->discoveryStatus('open', 'open', TicketRulesService::RELATIONSHIP));
        // Destinations (WhatsApp digits / e-mail): the CRM reply is delivered through them.
        $task->setAnnounce($destinations);
        $this->manager->persist($task);

        return $task;
    }

    /** @return array{ddi: string, ddd: string, phone: string}|null Brazilian numbers, DDI 55 when missing. */
    private function phone(string $raw): ?array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55' . $digits;
        }
        if (!preg_match('/^(\d{2})(\d{2})(\d{8,9})$/', $digits, $m)) {
            return null;
        }

        return ['ddi' => $m[1], 'ddd' => $m[2], 'phone' => $m[3]];
    }

    /** Page and campaign data travel with the first message so the CRM sees where the lead came from. */
    private function withSource(string $message, array $input): string
    {
        $source = [];
        foreach (['page_url' => 'Página', 'utm_source' => 'utm_source', 'utm_medium' => 'utm_medium', 'utm_campaign' => 'utm_campaign', 'gclid' => 'gclid', 'fbclid' => 'fbclid'] as $key => $label) {
            $value = mb_substr(trim(strip_tags((string) ($input[$key] ?? ''))), 0, 300);
            if ($value !== '') {
                $source[] = $label . ': ' . $value;
            }
        }

        return $source === [] ? $message : $message . "\n\n— " . implode(' · ', $source);
    }

    private function withinLimit(string $contact, string $ip): bool
    {
        foreach (array_filter(['c' => $contact, 'i' => $ip]) as $kind => $value) {
            $key = 'public_contact_' . $kind . '_' . hash('sha256', $value) . '_' . date('YmdH');
            $count = $this->cache->get($key, static function (ItemInterface $item) {
                $item->expiresAfter(3600);

                return 0;
            });
            if ($count >= self::MAX_PER_HOUR) {
                return false;
            }
            $this->cache->delete($key);
            $this->cache->get($key, static function (ItemInterface $item) use ($count) {
                $item->expiresAfter(3600);

                return $count + 1;
            });
        }

        return true;
    }
}
