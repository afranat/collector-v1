<?php

declare(strict_types=1);

namespace App\Presentation\Student\Profile;

use App\Model\IncentiveDemoService;
use Nette;

final class ProfilePresenter extends Nette\Application\UI\Presenter
{
    private const STUDENT_ID = 2;

    public function __construct(
        private readonly IncentiveDemoService $incentiveDemoService,
    ) {
        parent::__construct();
    }

    public function renderDefault(int $subjectId = 1): void
    {
        $subjects = $this->incentiveDemoService->getSubjects();
        $badges = $this->incentiveDemoService->getBadges();
        $summary = $this->incentiveDemoService->getStudentProfileSummary(self::STUDENT_ID, $subjectId);

        $this->template->subjects = $subjects;
        $this->template->subjectId = $subjectId;
        $this->template->badges = $badges;
        $this->template->summary = $summary;
    }
}
