<?php

declare(strict_types=1);

namespace App\Presentation\Student\Offers;

use App\Model\IncentiveDemoService;
use App\Presentation\BasePresenter;

final class OffersPresenter extends BasePresenter
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
        $offers = $this->incentiveDemoService->getOffersForSubject($subjectId);
        $claims = $this->incentiveDemoService->getClaimsForSubject($subjectId, $studentUserId);

        $this->template->subjects = $subjects;
        $this->template->subjectId = $subjectId;
        $this->template->offers = $offers;
        $this->template->claims = $claims;
    }

    public function handleAccept(int $offerId, int $subjectId = 1): void
    {

        $studentUserId = $this->requireStudentUserId();
        if ($studentUserId === null) {
            return;
        }

        $this->incentiveDemoService->acceptOffer($offerId, $studentUserId);
        $this->flashMessage('Pobídka byla přijata.');
        $this->redirect('default', ['subjectId' => $subjectId]);
    }

    public function handleSubmit(int $claimId, int $subjectId = 1): void
    {
        $studentUserId = $this->requireStudentUserId();
        if ($studentUserId === null) {
            return;
        }

        $this->incentiveDemoService->submitClaim($claimId, $studentUserId, 'Odevzdaný důkaz (demo).');
        $this->flashMessage('Důkaz byl odevzdán ke schválení.');
        $this->redirect('default', ['subjectId' => $subjectId]);
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
