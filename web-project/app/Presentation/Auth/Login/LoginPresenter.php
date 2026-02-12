<?php

declare(strict_types=1);

namespace App\Presentation\Auth\Login;

use App\Presentation\BasePresenter;
use Nette\Application\UI\Form;

final class LoginPresenter extends BasePresenter
{
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
        $form->addSelect('role', 'Role', [
            self::ROLE_STUDENT => 'Student',
            self::ROLE_TEACHER => 'Teacher',
            self::ROLE_ADMIN => 'Admin',
        ])->setRequired('Vyberte roli.');
        $form->addSubmit('send', 'Přihlásit se');

        $form->onSuccess[] = function (Form $form, \stdClass $values): void {
            $this->loginAs($values->role);

            if ($values->role === self::ROLE_STUDENT) {
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
