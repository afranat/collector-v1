<?php

declare(strict_types=1);

namespace App\Presentation\Teacher\Collaboration;

use App\Model\IncentiveDemoService;

use Nette\Application\UI\Form;
use App\Presentation\BasePresenter;

final class CollaborationPresenter extends BasePresenter
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

    public function actionDefault(int $subjectId = 1, ?int $offerId = null): void
    {
        $defaults = ['subjectId' => $subjectId];

        if ($offerId !== null) {
            foreach ($this->incentiveDemoService->getOffersForSubject($subjectId) as $offer) {
                if ($offer['id'] !== $offerId) {
                    continue;
                }

                $defaults['offerId'] = $offer['id'];
                $defaults['title'] = $offer['title'];
                $defaults['description'] = $offer['description'];
                $defaults['requiresApproval'] = $offer['requiresApproval'];
                $defaults['badgeIds'] = $offer['rewardBadgeIds'];
                break;
            }
        }

        $this['offerForm']->setDefaults($defaults);
    }

    protected function createComponentOfferForm(): Form
    {
        $form = new Form;
        $form->addHidden('subjectId');
        $form->addHidden('offerId');
        $form->addText('title', 'Název pobídky')
            ->setRequired('Vyplňte název pobídky.');
        $form->addTextArea('description', 'Popis')
            ->setRequired('Vyplňte popis.');
        $form->addCheckbox('requiresApproval', 'Vyžaduje schválení učitelem');
        $form->addCheckboxList('badgeIds', 'Odměna (odznaky)', []);
        $form->addSubmit('save', 'Uložit pobídku');

        $form->onAnchor[] = function () use ($form): void {

            $items = [];
            foreach ($this->incentiveDemoService->getBadges() as $badge) {
                $items[$badge['id']] = sprintf('%s (+%d EXP)', $badge['title'], $badge['expValue']);
            }
            $form['badgeIds']->setItems($items);
        };

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $badgeIds = array_map('intval', $values->badgeIds);
            $offerId = $values->offerId !== '' ? (int) $values->offerId : null;

            if ($offerId !== null) {
                $this->incentiveDemoService->updateOffer(
                    $offerId,
                    (int) $values->subjectId,
                    $values->title,
                    $values->description,
                    (bool) $values->requiresApproval,
                    $badgeIds,
                );
                $this->flashMessage('Pobídka byla upravena.');
            } else {
                $this->incentiveDemoService->addOffer(
                    (int) $values->subjectId,
                    $values->title,
                    $values->description,
                    (bool) $values->requiresApproval,
                    $badgeIds,
                );
                $this->flashMessage('Pobídka byla publikována.');
            }
            $this->redirect('default', ['subjectId' => (int) $values->subjectId]);
        };

        return $form;
    }
    public function handleDeleteOffer(int $offerId, int $subjectId): void
    {
        $this->incentiveDemoService->archiveOffer($offerId, $subjectId);
        $this->flashMessage('Pobídka byla smazána.');
        $this->redirect('default', ['subjectId' => $subjectId]);
    }
    public function handleApprove(int $claimId, int $subjectId=1): void
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
