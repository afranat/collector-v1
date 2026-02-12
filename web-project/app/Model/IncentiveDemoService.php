<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Http\Session;

final class IncentiveDemoService
{
    private const SECTION = 'incentiveDemo';

    public function __construct(
        private readonly Session $session,
    ) {
    }

    /**
     * @return array<int, array{id:int,title:string,ownerUserId:int,isArchived:bool}>
     */
    public function getSubjects(): array
    {
        $state = $this->getState();
        return $state['subjects'];
    }

    /**
     * @return array<int, array{id:int,title:string,description:string,expValue:int,badgeValue:int,isRepeatable:bool}>
     */
    public function getBadges(): array
    {
        $state = $this->getState();
        return $state['badges'];
    }

    /**
     * Backward-compatible alias: badges are now global for all subjects.
     *
     * @return array<int, array{id:int,title:string,description:string,expValue:int,badgeValue:int,isRepeatable:bool}>
     */
    public function getBadgesForSubject(int $subjectId): array
    {
        return $this->getBadges();
    }

    /**
     * @return array<int, array{id:int,subjectId:int,title:string,description:string,status:string,requiresApproval:bool,rewardBadgeIds:int[]}>
     */
    public function getOffersForSubject(int $subjectId): array
    {
        $state = $this->getState();
        return array_values(array_filter(
            $state['offers'],
            static fn (array $offer): bool => $offer['subjectId'] === $subjectId && $offer['status'] === 'published',
        ));
    }

    /**
     * @return array<int, array{id:int,offerId:int,studentUserId:int,status:string,evidence:string|null,decisionNote:string|null}>
     */
    public function getClaimsForSubject(int $subjectId): array
    {
        $state = $this->getState();
        $offerIds = array_map(
            static fn (array $offer): int => $offer['id'],
            array_filter($state['offers'], static fn (array $offer): bool => $offer['subjectId'] === $subjectId),
        );

        return array_values(array_filter(
            $state['claims'],
            static fn (array $claim): bool => in_array($claim['offerId'], $offerIds, true),
        ));
    }

    /**
     * @return array<int, array{id:int,subjectId:int,userId:int,sourceType:string,sourceId:int,badgeId:int|null,expDelta:int,badgeDelta:int}>
     */
    public function getLedgerForStudent(int $studentUserId, ?int $subjectId = null): array
    {
        $state = $this->getState();
        return array_values(array_filter(
            $state['ledger'],
            static fn (array $entry): bool => $entry['userId'] === $studentUserId
                && ($subjectId === null || $entry['subjectId'] === $subjectId),
        ));
    }

    /**
     * @return array{expTotal:int,badgeTotals:array<int,int>}
     */
    public function getStudentProfileSummary(int $studentUserId, int $subjectId): array
    {
        $ledger = $this->getLedgerForStudent($studentUserId, $subjectId);
        $expTotal = 0;
        $badgeTotals = [];

        foreach ($ledger as $entry) {
            $expTotal += $entry['expDelta'];
            if ($entry['badgeId'] !== null) {
                $badgeTotals[$entry['badgeId']] = ($badgeTotals[$entry['badgeId']] ?? 0) + $entry['badgeDelta'];
            }
        }

        return [
            'expTotal' => $expTotal,
            'badgeTotals' => $badgeTotals,
        ];
    }

    public function addSubject(string $title, int $teacherId): void
    {
        $state = $this->getState();
        $state['subjects'][] = [
            'id' => $this->nextId($state['subjects']),
            'title' => $title,
            'ownerUserId' => $teacherId,
            'isArchived' => false,
        ];
        $this->saveState($state);
    }

    public function addBadge(string $title, string $description, int $expValue, bool $isRepeatable): void
    {
        $state = $this->getState();
        $state['badges'][] = [
            'id' => $this->nextId($state['badges']),
            'title' => $title,
            'description' => $description,
            'expValue' => $expValue,
            'badgeValue' => 1,
            'isRepeatable' => $isRepeatable,
        ];
        $this->saveState($state);
    }

    /**
     * @param int[] $badgeIds
     */
    public function addOffer(int $subjectId, string $title, string $description, bool $requiresApproval, array $badgeIds): void
    {
        $state = $this->getState();
        $offerId = $this->nextId($state['offers']);
        $state['offers'][] = [
            'id' => $offerId,
            'subjectId' => $subjectId,
            'title' => $title,
            'description' => $description,
            'status' => 'published',
            'requiresApproval' => $requiresApproval,
            'rewardBadgeIds' => $badgeIds,
        ];
        $this->saveState($state);
    }

    public function acceptOffer(int $offerId, int $studentUserId): void
    {
        $state = $this->getState();
        $existing = $this->findClaimByOfferAndStudent($state['claims'], $offerId, $studentUserId);
        if ($existing !== null) {
            return;
        }

        $offer = $this->findOfferById($state['offers'], $offerId);
        if ($offer === null || $offer['status'] !== 'published') {
            return;
        }

        $claimId = $this->nextId($state['claims']);
        $status = $offer['requiresApproval'] ? 'accepted' : 'approved';
        $state['claims'][] = [
            'id' => $claimId,
            'offerId' => $offerId,
            'studentUserId' => $studentUserId,
            'status' => $status,
            'evidence' => null,
            'decisionNote' => null,
        ];

        if ($status === 'approved') {
            $this->appendLedgerForOffer($state, $offer, $studentUserId, $claimId);
        }

        $this->saveState($state);
    }

    public function submitClaim(int $claimId, int $studentUserId, string $evidence): void
    {
        $state = $this->getState();
        foreach ($state['claims'] as $index => $claim) {
            if ($claim['id'] === $claimId && $claim['studentUserId'] === $studentUserId && $claim['status'] === 'accepted') {
                $state['claims'][$index]['status'] = 'submitted';
                $state['claims'][$index]['evidence'] = $evidence;
                $this->saveState($state);
                return;
            }
        }
    }

    public function decideClaim(int $claimId, bool $approve, string $note, int $teacherUserId): void
    {
        $state = $this->getState();
        foreach ($state['claims'] as $index => $claim) {
            if ($claim['id'] !== $claimId || $claim['status'] !== 'submitted') {
                continue;
            }

            $offer = $this->findOfferById($state['offers'], $claim['offerId']);
            if ($offer === null) {
                return;
            }

            $state['claims'][$index]['status'] = $approve ? 'approved' : 'rejected';
            $state['claims'][$index]['decisionNote'] = $note;

            if ($approve) {
                $this->appendLedgerForOffer($state, $offer, $claim['studentUserId'], $claimId, $teacherUserId);
            }

            $this->saveState($state);
            return;
        }
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     */
    private function nextId(array $rows): int
    {
        if ($rows === []) {
            return 1;
        }

        return (int) max(array_column($rows, 'id')) + 1;
    }

    /**
     * @return array{subjects:array<int,array<string,mixed>>,badges:array<int,array<string,mixed>>,offers:array<int,array<string,mixed>>,claims:array<int,array<string,mixed>>,ledger:array<int,array<string,mixed>>}
     */
    private function getState(): array
    {
        $section = $this->session->getSection(self::SECTION);
        if (!isset($section->state)) {
            $section->state = $this->seedState();
        }

        /** @var array{subjects:array<int,array<string,mixed>>,badges:array<int,array<string,mixed>>,offers:array<int,array<string,mixed>>,claims:array<int,array<string,mixed>>,ledger:array<int,array<string,mixed>>} $state */
        $state = $section->state;
        return $state;
    }

    /**
     * @param array{subjects:array<int,array<string,mixed>>,badges:array<int,array<string,mixed>>,offers:array<int,array<string,mixed>>,claims:array<int,array<string,mixed>>,ledger:array<int,array<string,mixed>>} $state
     */
    private function saveState(array $state): void
    {
        $this->session->getSection(self::SECTION)->state = $state;
    }

    /**
     * @return array{subjects:array<int,array<string,mixed>>,badges:array<int,array<string,mixed>>,offers:array<int,array<string,mixed>>,claims:array<int,array<string,mixed>>,ledger:array<int,array<string,mixed>>}
     */
    private function seedState(): array
    {
        return [
            'subjects' => [
                ['id' => 1, 'title' => 'Matematika', 'ownerUserId' => 1, 'isArchived' => false],
            ],
            'badges' => [
                ['id' => 1, 'title' => 'Algebra Starter', 'description' => 'Vyřešeno 5 algebraických úloh', 'expValue' => 30, 'badgeValue' => 1, 'isRepeatable' => false],
                ['id' => 2, 'title' => 'Geometrie', 'description' => 'Správně spočtené obsahy 3 tvarů', 'expValue' => 20, 'badgeValue' => 1, 'isRepeatable' => true],
            ],
            'offers' => [
                ['id' => 1, 'subjectId' => 1, 'title' => 'Procvič kvadratické rovnice', 'description' => 'Vyřeš pracovní list A.', 'status' => 'published', 'requiresApproval' => true, 'rewardBadgeIds' => [1]],
                ['id' => 2, 'subjectId' => 1, 'title' => 'Krátký geotest', 'description' => 'Online mini test na geometrii.', 'status' => 'published', 'requiresApproval' => false, 'rewardBadgeIds' => [2]],
            ],
            'claims' => [],
            'ledger' => [],
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $offers
     * @return array<string,mixed>|null
     */
    private function findOfferById(array $offers, int $offerId): ?array
    {
        foreach ($offers as $offer) {
            if ($offer['id'] === $offerId) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string,mixed>> $claims
     * @return array<string,mixed>|null
     */
    private function findClaimByOfferAndStudent(array $claims, int $offerId, int $studentUserId): ?array
    {
        foreach ($claims as $claim) {
            if ($claim['offerId'] === $offerId && $claim['studentUserId'] === $studentUserId) {
                return $claim;
            }
        }

        return null;
    }

    /**
     * @param array{subjects:array<int,array<string,mixed>>,badges:array<int,array<string,mixed>>,offers:array<int,array<string,mixed>>,claims:array<int,array<string,mixed>>,ledger:array<int,array<string,mixed>>} $state
     * @param array<string,mixed> $offer
     */
    private function appendLedgerForOffer(array &$state, array $offer, int $studentUserId, int $claimId, int $createdBy = 1): void
    {
        foreach ($offer['rewardBadgeIds'] as $badgeId) {
            $badge = null;
            foreach ($state['badges'] as $candidate) {
                if ($candidate['id'] === $badgeId) {
                    $badge = $candidate;
                    break;
                }
            }

            if ($badge === null) {
                continue;
            }

            $state['ledger'][] = [
                'id' => $this->nextId($state['ledger']),
                'subjectId' => $offer['subjectId'],
                'userId' => $studentUserId,
                'sourceType' => 'offer_claim',
                'sourceId' => $claimId,
                'badgeId' => $badge['id'],
                'expDelta' => $badge['expValue'],
                'badgeDelta' => $badge['badgeValue'],
                'createdBy' => $createdBy,
            ];
        }
    }
}
