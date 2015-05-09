# ActionInjections

Master:
[![Build Status](https://travis-ci.org/t4web/ActionInjections.svg?branch=master)](https://travis-ci.org/t4web/ActionInjections)
[![codecov.io](http://codecov.io/github/t4web/ActionInjections/coverage.svg?branch=master)](http://codecov.io/github/t4web/ActionInjections?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/t4web/ActionInjections/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/t4web/ActionInjections/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/bf0786c4-5f02-4874-b3c1-67da3c35c40a/mini.png)](https://insight.sensiolabs.com/projects/bf0786c4-5f02-4874-b3c1-67da3c35c40a)
[![Dependency Status](https://www.versioneye.com/user/projects/554e00efbb6a1e3bdb000002/badge.svg?style=flat)](https://www.versioneye.com/user/projects/554e00efbb6a1e3bdb000002)

Introduction
------------
ZF2 Module wich allows to inject dependencies in controller action for better incapsulate testing and remove controller dependency from ServiceLocator.

_Problem_:
I have simply CRUD Controller, with actions "create", "update", "show", "delete", i have 3 dependency in action "delete": ViewModel, Request, some Service.
```php
class AjaxController extends Zend\Mvc\Controller\AbstractActionController
{
    public function deleteTimesheetAction() {
        $view = new ViewModel();
    
        if (!$this->getRequest()->isPost()) {
            return $view;
        }
        
        $timesheetDeleteService = $this->getServiceLocator()->get('Timesheet\Timesheet\Service\Delete');

        $timesheetId = $this->getRequest()->getPost()->get('id', 0);
        if (!$timesheetDeleteService->delete($timesheetId)) {
            $view->errors = $timesheetDeleteService->getErrors();
        }

        return $view;
    }
    //...
}

```
in this case i can't easy test this controller, because:
  1. I can't mock ViewModel (constructor calling)
  2. Very difficult create test for this $this->getRequest()->getPost()->get('id', 0);
  3. Nobody understand dependencies in current controller, because $this->getServiceLocator()->get('SomeService') inside controller - is bad practice
  
Ok, refactor it..

_Problem_:
I have simply CRUD Controller, with actions "create", "update", "show", "delete", i have 3 dependency in action "delete": ViewModel, Request, some Service. For height testability i add all dependencies in Controller::__constructor().
```php
class AjaxController extends Zend\Mvc\Controller\AbstractActionController
{
    /**
     * @var BaseFinder
     */
    private $timesheetFinder;

    /**
     * @var BaseFinder
     */
    private $calendarFinder;

    /**
     * @var CreateInterface
     */
    private $createService;

    /**
     * @var UpdateInterface
     */
    private $updateService;

    /**
     * @var DeleteInterface
     */
    private $deleteService;

    /**
     * @var AjaxViewModel
     */
    private $view;

    public function __construct(
        BaseFinder $timesheetFinder,
        BaseFinder $calendarFinder,
        CreateInterface $timesheetCreateService,
        UpdateInterface $timesheetUpdateService,
        DeleteInterface $timesheetDeleteService,
        AjaxViewModel $view)
    {

        $this->timesheetFinder = $timesheetFinder;
        $this->calendarFinder = $calendarFinder;
        $this->createService = $timesheetCreateService;
        $this->updateService = $timesheetUpdateService;
        $this->deleteService = $timesheetDeleteService;
        $this->view = $view;
    }
    
    public function deleteTimesheetAction() {
        $view = new ViewModel();
    
        if (!$this->getRequest()->isPost()) {
            return $this->view;
        }

        $timesheetId = $this->getRequest()->getPost()->get('id', 0);
        if (!$this->deleteService->delete($timesheetId)) {
            $this->view->errors = $this->deleteService->getErrors();
        }

        return $this->view;
    }
    // ...
}
```

```php
class AjaxControllerFactory implements FactoryInterface {

    public function createService(ServiceLocatorInterface $serviceLocator) {
        $serviceManager = $serviceLocator->getServiceLocator();
        return new AjaxController(
            $serviceManager->get('Timesheet\Timesheet\Service\Finder'),
            $serviceManager->get('Calendar\Calendar\Service\Finder'),
            $serviceManager->get('Timesheet\Timesheet\Service\Create'),
            $serviceManager->get('Timesheet\Timesheet\Service\Update'),
            $serviceManager->get('Timesheet\Timesheet\Service\Delete'),
            $serviceManager->get('Timesheet\Controller\ViewModel\AjaxViewModel')
        );
    }
}
```
in this case i can easy test this controller, but:
  1. I have to big __constructor (almost god object)
  2. How many mock's i must create for test on method "delete"?
  3. Nobody understand where i use each dependency in current controller
  4. I must test ControllerFactory
  
_Solution_: use `t4web/ActionInjections`
Add in your module.config.php section `controller_action_injections`
```php
    'controller_action_injections' => array(
        'Timesheet\Controller\User\AjaxController' => array(
            'deleteTimesheetAction' => array(
                'request',
                'Timesheet\Controller\ViewModel\AjaxViewModel',
                'Timesheet\Timesheet\Service\Delete',
            ),
        ),
    ),
```
where `request`, `Timesheet\Controller\ViewModel\AjaxViewModel`, `Timesheet\Timesheet\Service\Delete` your dependecies, and just use it in your controller action

```php
class AjaxController extends Zend\Mvc\Controller\AbstractActionController
{
    public function deleteTimesheetAction(HttpRequest $request, AjaxViewModel $view, DeleteInterface $timesheetDeleteService) {
        if (!$request->isPost()) {
            return $view;
        }

        $timesheetId = $request->getPost()->get('id', 0);
        if (!$timesheetDeleteService->delete($timesheetId)) {
            $view->setErrors($timesheetDeleteService->getErrors());
        }

        return $view;
    }
    //...
}
```
and test it:
```php
class AjaxControllerTest extends \PHPUnit_Framework_TestCase {

    public function testDeleteTimesheetAction_Delete_ReturnView() {
        $requestMock = $this->getMockBuilder('Zend\Http\PhpEnvironment\Request')->disableOriginalConstructor()->getMock();
        $timesheetDeleteServiceMock = $this->getMockBuilder('T4webBase\Domain\Service\Delete')->disableOriginalConstructor()->getMock();
        $ajaxViewModel = new AjaxViewModel();
    
        $timesheetId = 1;
        $parameters = new Parameters(array('id' => $timesheetId));

        $requestMock->expects($this->once())
            ->method('isPost')
            ->will($this->returnValue(true));

        $requestMock->expects($this->once())
            ->method('getPost')
            ->will($this->returnValue($parameters));

        $timesheetDeleteServiceMock->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($timesheetId))
            ->will($this->returnValue(true));
            
        $controller = new AjaxController();

        /** @var $result AjaxViewModel */
        $result = $controller->deleteTimesheetAction($requestMock, $ajaxViewModel, $timesheetDeleteServiceMock);

        $this->assertEquals($ajaxViewModel, $result);
    }
    //...
}
```
very fast, easy, readable, incapsulate unit test.

Requirements
------------
* [Zend Framework 2](https://github.com/zendframework/zf2) (latest master)

Installation
------------
### Main Setup

#### By cloning project

Clone this project into your `./vendor/` directory.

#### With composer

Add this project in your composer.json:

```json
"repositories": [
        {
            "type": "git",
            "url": "https://github.com/t4web/actioninjections.git"
        }
],

"require": {
    "t4web/actioninjections": "dev-master"
}
```

Now tell composer to download Authentication by running the command:

```bash
$ php composer.phar update
```

#### Post installation

Not need enabling it in your `application.config.php`file, just extends from `T4webActionInjections\Mvc\Controller\AbstractActionController`

Testing
------------
Unit test runnig from authentication module directory.
```bash
$ phpunit
```
