<?php

namespace T4webActionInjectionsTest\Unit\Mvc\Controller;

use Zend\ServiceManager\Exception\ServiceNotFoundException;

class AbstractActionControllerTest extends \PHPUnit_Framework_TestCase
{

    private $controller;
    private $routeMatchMock;
    private $mvcEventMock;
    private $serviceLocatorMock;

    public function setUp() {
        $this->routeMatchMock = $this->getMockBuilder('Zend\Mvc\Router\RouteMatch')->disableOriginalConstructor()->getMock();

        $this->mvcEventMock = $this->getMock('Zend\Mvc\MvcEvent');
        $this->mvcEventMock->expects($this->once())
            ->method('getRouteMatch')
            ->will($this->returnValue($this->routeMatchMock));

        $this->serviceLocatorMock = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');

        $this->controller = $this->getMock(
            'T4webActionInjectionsTest\Assets\IndexController',
            array('getServiceLocator', 'notFoundAction', 'indexAction', 'listAction'),
            array()
        );
        $this->controller->expects($this->once())
            ->method('getServiceLocator')
            ->will($this->returnValue($this->serviceLocatorMock));
    }

    public function testOnDispatch_ModuleConfigDoesNotContainControllerActionInjections_ExecuteMethodWithoutParams() {
        $config = array();
        $actionResponse = 'someResponse';
        $this->routeMatchMock->expects($this->once())
            ->method('getParam')
            ->will($this->returnValue('index'));

        $this->serviceLocatorMock->expects($this->once())
            ->method('get')
            ->with($this->equalTo('config'))
            ->will($this->returnValue($config));

        $this->controller->expects($this->once())
            ->method('indexAction')
            ->will($this->returnValue($actionResponse));

        $this->mvcEventMock->expects($this->once())
            ->method('setResult')
            ->with($this->equalTo($actionResponse));

        $result = $this->controller->onDispatch($this->mvcEventMock);

        $this->assertEquals($actionResponse, $result);
    }

    public function testOnDispatch_ModuleConfigContainControllerActionInjections_ExecuteMethodWithParams() {
        $config = array(
            'controller_action_injections' => array(
                get_class($this->controller) => array(
                    'listAction' => array(
                        'PATH_TO_SAME_ACTION_DEPENDENCE\LIKE_IN_MODULE.PHP',
                    ),
                ),
            ),
        );
        $someDependency = 'someDependency';
        $actionResponse = 'someResponse';
        $this->routeMatchMock->expects($this->once())
            ->method('getParam')
            ->will($this->returnValue('list'));

        $this->serviceLocatorMock->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('config'))
            ->will($this->returnValue($config));

        $this->serviceLocatorMock->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('PATH_TO_SAME_ACTION_DEPENDENCE\LIKE_IN_MODULE.PHP'))
            ->will($this->returnValue($someDependency));

        $this->controller->expects($this->once())
            ->method('listAction')
            ->with($this->equalTo($someDependency))
            ->will($this->returnValue($actionResponse));

        $this->mvcEventMock->expects($this->once())
            ->method('setResult')
            ->with($this->equalTo($actionResponse));

        $result = $this->controller->onDispatch($this->mvcEventMock);

        $this->assertEquals($actionResponse, $result);
    }

    /**
     * @expectedException \T4webActionInjections\Mvc\Controller\Exception\DependencyNotResolvedException
     * @expectedExceptionMessage Controller action dependency not resolved: exception message from Zend\ServiceManager\ServiceManager
     */
    public function testOnDispatch_ModuleConfigContainControllerActionInjections_ThrowException() {
        $config = array(
            'controller_action_injections' => array(
                get_class($this->controller) => array(
                    'listAction' => array(
                        'BAD_PATH_TO_SAME_ACTION_DEPENDENCE\LIKE_IN_MODULE.PHP',
                    ),
                ),
            ),
        );
        $this->routeMatchMock->expects($this->once())
            ->method('getParam')
            ->will($this->returnValue('list'));

        $this->serviceLocatorMock->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo('config'))
            ->will($this->returnValue($config));

        $this->serviceLocatorMock->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('BAD_PATH_TO_SAME_ACTION_DEPENDENCE\LIKE_IN_MODULE.PHP'))
            ->will($this->throwException( new ServiceNotFoundException('exception message from Zend\ServiceManager\ServiceManager') ));

        $this->controller->expects($this->never())
            ->method('listAction');

        $this->mvcEventMock->expects($this->never())
            ->method('setResult');

        $this->controller->onDispatch($this->mvcEventMock);
    }
}
