<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;

final class IncentiveDemoService
{
    public function __construct(
        private readonly Explorer $database,
    ) {
    }

    /**
     * @return array<int, array{id:int,name:string,email:string}>
     */
    public function getStudents(): array
    {
        $this->ensureDemoUsers();

        return array_map(
            static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
            ],
            $this->database->table('user_account')->where('role', 'student')->order('id ASC')->fetchAll(),
        );
    }

    /**
     * @return array<int, array{id:int,title:string,ownerUserId:int,isArchived:bool}>
     */
    public function getSubjects(): array
    {
        return array_map(
            static fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'ownerUserId' => (int) $row->owner_user_id,
                'isArchived' => (bool) $row->is_archived,
            ],
            $this->database->table('subject')->order('id ASC')->fetchAll(),
        );
    }

    /**
     * @return array<int, array{id:int,title:string,description:string,expValue:int,badgeValue:int,isRepeatable:bool}>
     */
    public function getBadges(): array
    {
        return array_map(
            static fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'description' => (string) ($row->description ?? ''),
                'expValue' => (int) $row->exp_value,
                'badgeValue' => (int) $row->badge_value,
                'isRepeatable' => (bool) $row->is_repeatable,
            ],
            $this->database->table('badge')->order('id ASC')->fetchAll(),
        );
    }

    /**
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
        $offers = $this->database->table('offer')
            ->where('subject_id', $subjectId)
            ->where('status', 'published')
            ->order('id ASC')
            ->fetchAll();

        $result = [];
        foreach ($offers as $offer) {
            $badgeIds = array_map(
                static fn ($reward): int => (int) $reward->badge_id,
                $this->database->table('offer_reward')->where('offer_id', $offer->id)->fetchAll(),
            );

            $result[] = [
                'id' => (int) $offer->id,
                'subjectId' => (int) $offer->subject_id,
                'title' => (string) $offer->title,
                'description' => (string) ($offer->description ?? ''),
                'status' => (string) $offer->status,
                'requiresApproval' => (bool) $offer->requires_approval,
                'rewardBadgeIds' => $badgeIds,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{id:int,offerId:int,studentUserId:int,status:string,evidence:string|null,decisionNote:string|null}>
     */
    public function getClaimsForSubject(int $subjectId, ?int $studentUserId = null): array
    {
        $selection = $this->database->table('offer_claim')
            ->where('offer.subject_id', $subjectId)
            ->order('id ASC');

        if ($studentUserId !== null) {
            $selection->where('student_user_id', $studentUserId);
        }

        $claims = $selection->fetchAll();;

        return array_map(
            static fn ($claim): array => [
                'id' => (int) $claim->id,
                'offerId' => (int) $claim->offer_id,
                'studentUserId' => (int) $claim->student_user_id,
                'status' => (string) $claim->status,
                'evidence' => null,
                'decisionNote' => $claim->decision_note,
            ],
            $claims,
        );
    }

    /**
     * @return array<int, array{id:int,subjectId:int,userId:int,sourceType:string,sourceId:int,badgeId:int|null,expDelta:int,badgeDelta:int}>
     */
    public function getLedgerForStudent(int $studentUserId, ?int $subjectId = null): array
    {
        $selection = $this->database->table('reward_ledger')->where('user_id', $studentUserId)->order('id ASC');
        if ($subjectId !== null) {
            $selection->where('subject_id', $subjectId);
        }

        return array_map(
            static fn ($row): array => [
                'id' => (int) $row->id,
                'subjectId' => (int) $row->subject_id,
                'userId' => (int) $row->user_id,
                'sourceType' => (string) $row->source_type,
                'sourceId' => (int) $row->source_id,
                'badgeId' => $row->badge_id !== null ? (int) $row->badge_id : null,
                'expDelta' => (int) $row->exp_delta,
                'badgeDelta' => (int) $row->badge_delta,
            ],
            $selection->fetchAll(),
        );
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

        return ['expTotal' => $expTotal, 'badgeTotals' => $badgeTotals];
    }

    public function addSubject(string $title, int $teacherId): void
    {
        $this->ensureDemoUsers();
        $this->database->table('subject')->insert([
            'title' => $title,
            'owner_user_id' => $teacherId,
            'is_archived' => 0,
        ]);
    }

    public function addBadge(string $title, string $description, int $expValue, bool $isRepeatable): void
    {
        $this->ensureDemoUsers();
        $subjectId = $this->getOrCreateDefaultSubjectId();

        $this->database->table('badge')->insert([
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'exp_value' => $expValue,
            'badge_value' => 1,
            'is_repeatable' => $isRepeatable ? 1 : 0,
        ]);
    }

    /**
     * @param int[] $badgeIds
     */
    public function addOffer(int $subjectId, string $title, string $description, bool $requiresApproval, array $badgeIds): void
    {
        $this->ensureDemoUsers();
        $offer = $this->database->table('offer')->insert([
            'subject_id' => $subjectId,
            'created_by' => 1,
            'title' => $title,
            'description' => $description,
            'status' => 'published',
            'requires_approval' => $requiresApproval ? 1 : 0,
        ]);

        foreach ($badgeIds as $badgeId) {
            $this->database->table('offer_reward')->insert([
                'offer_id' => $offer->id,
                'badge_id' => $badgeId,
                'qty' => 1,
                'exp_bonus' => 0,
            ]);
        }
    }
    /**
     * @param int[] $badgeIds
     */
    public function updateOffer(int $offerId, int $subjectId, string $title, string $description, bool $requiresApproval, array $badgeIds): void
    {
        $offer = $this->database->table('offer')
            ->where('id', $offerId)
            ->where('subject_id', $subjectId)
            ->where('status', 'published')
            ->fetch();

        if ($offer === null) {
            return;
        }

        $offer->update([
            'title' => $title,
            'description' => $description,
            'requires_approval' => $requiresApproval ? 1 : 0,
        ]);

        $this->database->table('offer_reward')->where('offer_id', $offerId)->delete();
        foreach ($badgeIds as $badgeId) {
            $this->database->table('offer_reward')->insert([
                'offer_id' => $offerId,
                'badge_id' => $badgeId,
                'qty' => 1,
                'exp_bonus' => 0,
            ]);
        }
    }

    public function archiveOffer(int $offerId, int $subjectId): void
    {
        $offer = $this->database->table('offer')
            ->where('id', $offerId)
            ->where('subject_id', $subjectId)
            ->where('status', 'published')
            ->fetch();

        if ($offer === null) {
            return;
        }

        $offer->update(['status' => 'archived']);
    }

    public function acceptOffer(int $offerId, int $studentUserId): void
    {
        $this->ensureDemoUsers();
        $existing = $this->database->table('offer_claim')
            ->where('offer_id', $offerId)
            ->where('student_user_id', $studentUserId)
            ->fetch();

        if ($existing !== null) {
            return;
        }

        $offer = $this->database->table('offer')->get($offerId);
        if ($offer === null || $offer->status !== 'published') {
            return;
        }

        $status = (bool) $offer->requires_approval ? 'accepted' : 'approved';
        $claim = $this->database->table('offer_claim')->insert([
            'offer_id' => $offerId,
            'student_user_id' => $studentUserId,
            'status' => $status,
            'accepted_at' => new \DateTimeImmutable(),
        ]);

        if ($status === 'approved') {
            $this->appendLedgerForOffer((int) $offer->id, $studentUserId, (int) $claim->id, 1);
        }
    }

    public function submitClaim(int $claimId, int $studentUserId, string $evidence): void
    {
        $claim = $this->database->table('offer_claim')
            ->where('id', $claimId)
            ->where('student_user_id', $studentUserId)
            ->where('status', 'accepted')
            ->fetch();

        if ($claim === null) {
            return;
        }

        $claim->update([
            'status' => 'submitted',
            'submitted_at' => new \DateTimeImmutable(),
        ]);

        $this->database->table('offer_claim_evidence')->insert([
            'claim_id' => $claimId,
            'type' => 'text',
            'value' => $evidence,
        ]);
    }

    public function decideClaim(int $claimId, bool $approve, string $note, int $teacherUserId): void
    {
        $claim = $this->database->table('offer_claim')
            ->where('id', $claimId)
            ->where('status', 'submitted')
            ->fetch();

        if ($claim === null) {
            return;
        }

        $claim->update([
            'status' => $approve ? 'approved' : 'rejected',
            'decision_note' => $note,
            'decided_at' => new \DateTimeImmutable(),
            'decided_by' => $teacherUserId,
        ]);

        if ($approve) {
            $this->appendLedgerForOffer((int) $claim->offer_id, (int) $claim->student_user_id, (int) $claimId, $teacherUserId);
        }
    }

    private function appendLedgerForOffer(int $offerId, int $studentUserId, int $claimId, int $createdBy): void
    {
        $offer = $this->database->table('offer')->get($offerId);
        if ($offer === null) {
            return;
        }

        $rewards = $this->database->table('offer_reward')->where('offer_id', $offerId)->fetchAll();
        foreach ($rewards as $reward) {
            $badge = $this->database->table('badge')->get($reward->badge_id);
            if ($badge === null) {
                continue;
            }

            $this->database->table('reward_ledger')->insert([
                'subject_id' => $offer->subject_id,
                'user_id' => $studentUserId,
                'source_type' => 'offer_claim',
                'source_id' => $claimId,
                'badge_id' => $badge->id,
                'exp_delta' => $badge->exp_value,
                'badge_delta' => $badge->badge_value,
                'created_by' => $createdBy,
            ]);
        }
    }

    private function ensureDemoUsers(): void
    {
        $teacher = $this->database->table('user_account')->where('id', 1)->fetch();
        if ($teacher === null) {
            $this->database->table('user_account')->insert([
                'id' => 1,
                'email' => 'teacher@example.local',
                'name' => 'Demo Teacher',
                'role' => 'teacher',
                'secret' => 'teacher',
            ]);
        } elseif (empty($teacher->secret)) {
            $teacher->update(['secret' => 'teacher']);
        }

        $student = $this->database->table('user_account')->where('id', 2)->fetch();
        if ($student === null) {
            $this->database->table('user_account')->insert([
                'id' => 2,
                'email' => 'student@example.local',
                'name' => 'Demo Student',
                'role' => 'student',
                'secret' => 'student',
            ]);
        } elseif (empty($student->secret)) {
            $student->update(['secret' => 'student']);
        }
    }

    private function getOrCreateDefaultSubjectId(): int
    {
        $subject = $this->database->table('subject')->order('id ASC')->fetch();
        if ($subject !== null) {
            return (int) $subject->id;
        }

        $this->ensureDemoUsers();
        $inserted = $this->database->table('subject')->insert([
            'title' => 'Obecné',
            'owner_user_id' => 1,
            'is_archived' => 0,
        ]);

        return (int) $inserted->id;
    }
}
