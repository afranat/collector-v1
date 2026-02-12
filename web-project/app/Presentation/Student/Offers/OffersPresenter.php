<?php

declare(strict_types=1);

namespace App\Presentation\Student\Offers;

use App\Model\IncentiveDemoService;
use App\Presentation\BasePresenter;

final class OffersPresenter extends BasePresenter
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
        $offers = $this->incentiveDemoService->getOffersForSubject($subjectId);
        $claims = $this->incentiveDemoService->getClaimsForSubject($subjectId);

        $this->template->subjects = $subjects;
        $this->template->subjectId = $subjectId;
        $this->template->offers = $offers;
        $this->template->claims = $claims;
    }

    public function handleAccept(int $offerId, int $subjectId = 1): void
    {
        $this->incentiveDemoService->acceptOffer($offerId, self::STUDENT_ID);
        $this->flashMessage('Pobídka byla přijata.');
        $this->redirect('default', ['subjectId' => $subjectId]);
    }

    public function handleSubmit(int $claimId, int $subjectId = 1): void
    {
        $this->incentiveDemoService->submitClaim($claimId, self::STUDENT_ID, 'Odevzdaný důkaz (demo).');
        $this->flashMessage('Důkaz byl odevzdán ke schválení.');
        $this->redirect('default', ['subjectId' => $subjectId]);
    }
}
