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
     * Controller class name
     *
     * @var string
     */
    private $requestedName;

    /**
     * @return string
     */
    public function getRequestedName()
    {
        return $this->requestedName;
    }

    /**
     * @param string $requestedName
     */
    public function setRequestedName($requestedName)
    {
        $this->requestedName = $requestedName;
    }

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
            throw new Exception\DomainException('Missing route matches; unsure how to retrieve action');
        }

        $action = $routeMatch->getParam('action', 'not-found');
        $method = static::getMethodFromAction($action);

        if (!method_exists($this, $method)) {
            $method = 'notFoundAction';
        }

        $className = get_class($this);
        if (!empty($this->requestedName)) {
            $className = $this->requestedName;
        }

        $actionResponse = call_user_func_array(array($this, $method), $this->getActionDependencies($className, $method));

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
                if (is_callable($dependency)) {
                    $dependencies[] = $dependency($serviceLocator, $this);
                    continue;
                }

                $dependencies[] = $serviceLocator->get($dependency);
            } catch (ServiceNotFoundException $e) {
                throw new DependencyNotResolvedException('Controller action dependency not resolved: ' . $e->getMessage());
            }
        }

        return $dependencies;
    }
}
