<?php

declare(strict_types=1);

namespace App\Presentation\Auth\Login;

use App\Model\IncentiveDemoService;
use App\Presentation\BasePresenter;
use Nette\Database\Explorer;
use Nette\Application\UI\Form;

final class LoginPresenter extends BasePresenter
{
    public function __construct(
        private readonly IncentiveDemoService $incentiveDemoService,
    ) {
        parent::__construct();
    }


    public function actionDefault(): void
    {
        $role = $this->getCurrentRole();

        if ($role === self::ROLE_STUDENT) {
            $this->redirect(':Student:Profile:default');
        }

        if ($this->isTeacherLikeRole()) {
            $this->redirect(':Teacher:Subjects:default');
        }
    }

    protected function createComponentLoginForm(): Form
    {
        $students = $this->incentiveDemoService->getStudents();
        $studentOptions = [];
        foreach ($students as $student) {
            $studentOptions[$student['id']] = sprintf('%s (%s)', $student['name'], $student['email']);
        }

        $form = new Form;
        //$form->addSelect('studentUserId', 'Student účet', $studentOptions)
        //    ->setPrompt('Vyberte studenta');
        $form->addPassword('secret', 'Tajné slovo')
            ->setRequired('Zadejte tajné slovo.');

        $form->addSubmit('send', 'Přihlásit se');
        $form->onValidate[] = function (Form $form, \stdClass $values): void {


            if ($values->studentUserId === null || $values->studentUserId === '') {
                $form['studentUserId']->addError('Vyberte studentský účet.');
            }
        };
        $form->onSuccess[] = function (Form $form, \stdClass $values): void {

            $userAccount = $this->database->table('user_account')
                ->where('secret', trim($values->secret))
                ->fetch();

            if ($userAccount === null || !is_string($userAccount->role)) {
                $form->addError('Neplatné tajné slovo.');
                return;
            }

            $role = $userAccount->role;
            if (!in_array($role, [self::ROLE_STUDENT, self::ROLE_TEACHER, self::ROLE_ADMIN], true)) {
                $form->addError('K účtu je přiřazena neplatná role.');
                return;
            }

            $this->loginAs($role);

            if ($role === self::ROLE_STUDENT) {
                $this->loginAsUser(self::ROLE_STUDENT, (int) $values->studentUserId);
                $this->redirect(':Student:Profile:default');
            }

            $this->redirect(':Teacher:Subjects:default');
        };

        return $form;
    }

    public function actionLogout(): void
    {
        $this->logout();
        $this->flashMessage('Byli jste odhlášeni.');
        $this->redirect('default');
    }
    public function handleLogout(): void
    {
        $this->actionLogout();
    }
}
