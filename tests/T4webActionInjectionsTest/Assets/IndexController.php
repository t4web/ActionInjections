<?php

namespace T4webActionInjectionsTest\Assets;

use T4webActionInjections\Mvc\Controller\AbstractActionController;

class IndexController extends AbstractActionController
{

    public function listAction($someDependency)
    {
        return $this->view;
    }

}
