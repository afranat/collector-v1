<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private readonly Nette\Database\Explorer $database,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {


        $this->template->subjects = $this->database->table('subject')->fetchAll();
    }
}
