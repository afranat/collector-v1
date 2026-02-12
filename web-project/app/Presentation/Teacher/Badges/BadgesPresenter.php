<?php

declare(strict_types=1);

namespace App\Presentation\Teacher\Badges;

use App\Model\IncentiveDemoService;
use Nette;
use Nette\Application\UI\Form;

final class BadgesPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private readonly IncentiveDemoService $incentiveDemoService,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $this->template->badges = $this->incentiveDemoService->getBadges();
    }

    protected function createComponentBadgeForm(): Form
    {
        $form = new Form;
        $form->addText('title', 'Název odznaku')
            ->setRequired('Zadejte název odznaku.');
        $form->addTextArea('description', 'Popis')
            ->setRequired('Vyplňte popis odznaku.');
        $form->addInteger('expValue', 'EXP hodnota')
            ->setDefaultValue(20)
            ->setRequired('Vyplňte EXP.');
        $form->addCheckbox('isRepeatable', 'Opakovatelný odznak');
        $form->addSubmit('save', 'Vytvořit odznak');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $this->incentiveDemoService->addBadge(
                $values->title,
                $values->description,
                (int) $values->expValue,
                (bool) $values->isRepeatable,
            );
            $this->flashMessage('Globální odznak byl vytvořen.');
            $this->redirect('default');
        };

        return $form;
    }

}
