<?php

declare(strict_types=1);

namespace App\Presentation\Teacher\Collaboration;

use App\Model\IncentiveDemoService;
use Nette;
use Nette\Application\UI\Form;

final class CollaborationPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private readonly IncentiveDemoService $incentiveDemoService,
    ) {
        parent::__construct();
    }

    public function renderDefault(int $subjectId = 1): void
    {
        $this->template->subjects = $this->incentiveDemoService->getSubjects();
        $this->template->subjectId = $subjectId;
        $this->template->badges = $this->incentiveDemoService->getBadges();
        $this->template->offers = $this->incentiveDemoService->getOffersForSubject($subjectId);
        $this->template->claims = $this->incentiveDemoService->getClaimsForSubject($subjectId);
    }

    public function actionDefault(int $subjectId = 1): void
    {
        $this['offerForm']->setDefaults(['subjectId' => $subjectId]);
    }

    protected function createComponentOfferForm(): Form
    {
        $form = new Form;
        $form->addHidden('subjectId');
        $form->addText('title', 'Název pobídky')
            ->setRequired('Vyplňte název pobídky.');
        $form->addTextArea('description', 'Popis')
            ->setRequired('Vyplňte popis.');
        $form->addCheckbox('requiresApproval', 'Vyžaduje schválení učitelem');
        $form->addCheckboxList('badgeIds', 'Odměna (odznaky)', []);
        $form->addSubmit('save', 'Publikovat pobídku');

        $form->onAnchor[] = function () use ($form): void {
            $subjectId = (int) ($form['subjectId']->getValue() ?: $this->getParameter('subjectId', 1));
            $items = [];
            foreach ($this->incentiveDemoService->getBadges() as $badge) {
                $items[$badge['id']] = sprintf('%s (+%d EXP)', $badge['title'], $badge['expValue']);
            }
            $form['badgeIds']->setItems($items);
        };

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $badgeIds = array_map('intval', $values->badgeIds);
            $this->incentiveDemoService->addOffer(
                (int) $values->subjectId,
                $values->title,
                $values->description,
                (bool) $values->requiresApproval,
                $badgeIds,
            );
            $this->flashMessage('Pobídka byla publikována.');
            $this->redirect('default', ['subjectId' => (int) $values->subjectId]);
        };

        return $form;
    }

    public function handleApprove(int $claimId, int $subjectId): void
    {
        $this->incentiveDemoService->decideClaim($claimId, true, 'Schváleno učitelem.', 1);
        $this->flashMessage('Claim byl schválen.');
        $this->redirect('default', ['subjectId' => $subjectId]);
    }

    public function handleReject(int $claimId, int $subjectId): void
    {
        $this->incentiveDemoService->decideClaim($claimId, false, 'Potřeba doplnit důkaz.', 1);
        $this->flashMessage('Claim byl zamítnut.');
        $this->redirect('default', ['subjectId' => $subjectId]);
    }
}
