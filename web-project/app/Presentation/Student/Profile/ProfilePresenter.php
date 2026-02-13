<?php

declare(strict_types=1);

namespace App\Presentation\Student\Profile;

use App\Model\IncentiveDemoService;
use App\Presentation\BasePresenter;

final class ProfilePresenter extends BasePresenter
{


    public function __construct(
        private readonly IncentiveDemoService $incentiveDemoService,
    ) {
        parent::__construct();
    }

    public function renderDefault(int $subjectId = 1): void
    {
        $studentUserId = $this->requireStudentUserId();
        if ($studentUserId === null) {
            return;
        }

        $subjects = $this->incentiveDemoService->getSubjects();
        $badges = $this->incentiveDemoService->getBadges();
        $summary = $this->incentiveDemoService->getStudentProfileSummary($studentUserId, $subjectId);

        $this->template->subjects = $subjects;
        $this->template->subjectId = $subjectId;
        $this->template->badges = $badges;
        $this->template->summary = $summary;
    }
    private function requireStudentUserId(): ?int
    {
        $studentUserId = $this->getCurrentUserId();
        if ($studentUserId !== null) {
            return $studentUserId;
        }

        $this->flashMessage('Pro studentskou část vyberte při přihlášení konkrétní účet.', 'warning');
        $this->redirect(':Auth:Login:default');

        return null;
    }
}
