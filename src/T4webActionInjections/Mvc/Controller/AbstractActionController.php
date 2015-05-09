<?php

namespace T4webActionInjections\Mvc\Controller;

use Zend\Mvc\Controller\AbstractActionController as ZendAbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Exception;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use T4webActionInjections\Mvc\Controller\Exception\DependencyNotResolvedException;

class AbstractActionController extends ZendAbstractActionController
{

    /**
     * Execute the request
     *
     * @param  MvcEvent $e
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            /**
             * @todo Determine requirements for when route match is missing.
             *       Potentially allow pulling directly from request metadata?
             */
            throw new Exception\DomainException('Missing route matches; unsure how to retrieve action');
        }

        $action = $routeMatch->getParam('action', 'not-found');
        $method = static::getMethodFromAction($action);

        if (!method_exists($this, $method)) {
            $method = 'notFoundAction';
        }

        $actionResponse = call_user_func_array(array($this, $method), $this->getActionDependencies(get_class($this), $method));

        $e->setResult($actionResponse);

        return $actionResponse;
    }

    protected function getActionDependencies($controllerName, $method)
    {
        $dependencies = [];
        $serviceLocator = $this->getServiceLocator();
        $config = $serviceLocator->get('config');
        $actionDependencies = [];
        if (isset($config['controller_action_injections'][$controllerName][$method])) {
            $actionDependencies = $config['controller_action_injections'][$controllerName][$method];
        }

        foreach ($actionDependencies as $dependency) {
            try {
                $dependencies[] = $serviceLocator->get($dependency);
            } catch (ServiceNotFoundException $e) {
                throw new DependencyNotResolvedException('Controller action dependency not resolved: ' . $e->getMessage());
            }
         }

        return $dependencies;
    }
}
