<?php

declare(strict_types=1);

namespace App\Presentation\Teacher\Subjects;

use App\Model\IncentiveDemoService;

use Nette\Application\UI\Form;
use App\Presentation\BasePresenter;

final class SubjectsPresenter extends BasePresenter
{
    private const TEACHER_ID = 1;

    public function __construct(
        private readonly IncentiveDemoService $incentiveDemoService,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $this->template->subjects = $this->incentiveDemoService->getSubjects();
    }

    protected function createComponentSubjectForm(): Form
    {
        $form = new Form;
        $form->addText('title', 'Název tématu')
            ->setRequired('Zadejte název tématu.');
        $form->addSubmit('save', 'Vytvořit téma');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $this->incentiveDemoService->addSubject($values->title, self::TEACHER_ID);
            $this->flashMessage('Téma bylo vytvořeno.');
            $this->redirect('default');
        };

        return $form;
    }
}
