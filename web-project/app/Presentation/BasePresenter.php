<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    public const ROLE_STUDENT = 'student';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_ADMIN = 'admin';

    private const SESSION_SECTION = 'auth';
    private const SESSION_ROLE_KEY = 'role';
    private const SESSION_USER_ID_KEY = 'userId';

    private const SESSION_USER_NAME_KEY = 'userName';

    protected function startup(): void
    {
        parent::startup();

        if (!$this->isPublicPresenter() && $this->getCurrentRole() === null) {
            $this->redirect(':Auth:Login:default');
        }

        if ($this->isCurrentPresenter('Teacher') && !$this->isTeacherLikeRole()) {
            $this->flashMessage('Do učitelské části nemáte přístup.', 'warning');
            $this->redirect(':Student:Profile:default');
        }

        if ($this->isCurrentPresenter('Student') && $this->isTeacherLikeRole()) {
            $this->flashMessage('Do studentské části nemáte přístup.', 'warning');
            $this->redirect(':Teacher:Subjects:default');
        }
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $role = $this->getCurrentRole();
        $userId = $this->getCurrentUserId();
        $this->template->currentRole = $role;
        $this->template->currentUserId = $userId;
        $this->template->isLoggedIn = $role !== null;
        $this->template->currentUserName = $this->getCurrentUserName();
        $this->template->isTeacherRole = $this->isTeacherLikeRole();
        $this->template->isStudentRole = $role === self::ROLE_STUDENT;
    }

    protected function getCurrentRole(): ?string
    {
        $session = $this->getSession(self::SESSION_SECTION);
        $role = $session->{self::SESSION_ROLE_KEY} ?? null;

        return in_array($role, [self::ROLE_STUDENT, self::ROLE_TEACHER, self::ROLE_ADMIN], true) ? $role : null;
    }

    protected function loginAs(string $role, ?string $userName = null): void
    {
        $this->loginAsUser($role, null);
    }
    protected function loginAsUser(string $role, ?int $userId): void
    {
        $session = $this->getSession(self::SESSION_SECTION);
        $session->{self::SESSION_ROLE_KEY} = $role;
        if ($userId !== null) {
            $session->{self::SESSION_USER_ID_KEY} = $userId;
            return;
        }

        unset($session->{self::SESSION_USER_ID_KEY});
    }
    protected function getCurrentUserName(): ?string
    {
        $session = $this->getSession(self::SESSION_SECTION);
        $userName = $session->{self::SESSION_USER_NAME_KEY} ?? null;

        return is_string($userName) && $userName !== '' ? $userName : null;

    }

    protected function logout(): void
    {
        $session = $this->getSession(self::SESSION_SECTION);
        unset($session->{self::SESSION_ROLE_KEY}, $session->{self::SESSION_USER_NAME_KEY});
        unset($session->{self::SESSION_USER_ID_KEY});
    }

    protected function getCurrentUserId(): ?int
    {
        $session = $this->getSession(self::SESSION_SECTION);
        $userId = $session->{self::SESSION_USER_ID_KEY} ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    protected function isTeacherLikeRole(): bool
    {
        return in_array($this->getCurrentRole(), [self::ROLE_TEACHER, self::ROLE_ADMIN], true);
    }

    private function isPublicPresenter(): bool
    {
        return str_starts_with($this->getName(), 'Error:') || $this->getName() === 'Auth:Login';
    }

    private function isCurrentPresenter(string $module): bool
    {
        return str_starts_with($this->getName(), $module . ':');
    }
}
