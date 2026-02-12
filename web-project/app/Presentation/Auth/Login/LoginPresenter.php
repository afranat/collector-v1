<?php

declare(strict_types=1);

namespace App\Presentation\Auth\Login;

use App\Presentation\BasePresenter;
use Nette\Database\Explorer;
use Nette\Application\UI\Form;

final class LoginPresenter extends BasePresenter
{
    public function __construct(
        private readonly Explorer $database,
    ) {
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
        $form = new Form;
        $form->addPassword('secret', 'Tajné slovo')
            ->setRequired('Zadejte tajné slovo.');

        $form->addSubmit('send', 'Přihlásit se');

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
                $this->redirect(':Student:Profile:default');
            }

            $this->redirect(':Teacher:Subjects:default');
        };

        return $form;
    }

    public function handleLogout(): void
    {
        $this->logout();
        $this->flashMessage('Byli jste odhlášeni.');
        $this->redirect('default');
    }
}
