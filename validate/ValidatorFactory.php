<?php

namespace Lighwand\Validate;

use RuntimeException;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Validator\ValidatorChain;
use Zend\Validator\ValidatorInterface;

class ValidatorFactory implements AbstractFactoryInterface
{
    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return mixed
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return $this->create($this->getConfig($serviceLocator)['validators'][$requestedName], $serviceLocator);
    }

    /**
     * @param array $config
     * @param ServiceLocatorInterface $serviceLocator
     * @return ValidatorChain
     */
    private function create(array $config, ServiceLocatorInterface $serviceLocator)
    {
        $validator = new ValidatorChain();
        foreach ($config as $key => $val) {
            $breakChainOnFailure = false;
            if ($key === 'required') {
                continue;
            }
            if (is_string($key) && class_exists($key)) {
                if (isset($val['break']) || is_int($key)) {
                    $breakChainOnFailure = $val['break'];
                }
                $childValidator = $this->service($key, $serviceLocator);
            } elseif (is_array($val)) {
                $childValidator = $this->create($val, $serviceLocator);
                if (!isset($val['required']) || $val['required'] !== false) {
                    $childValidator->attach(new FieldExists($key), true, 2);
                }
            } else {
                $childValidator = $this->service($val, $serviceLocator);
            }
            $validator->attach($childValidator, $breakChainOnFailure);
        }
        return $validator;
    }

    /**
     * @param string $name
     * @param ServiceLocatorInterface $serviceLocator
     * @return ValidatorInterface
     */
    private function service($name, ServiceLocatorInterface $serviceLocator)
    {
        $validator = $serviceLocator->get($name);
        if (!$validator instanceof ValidatorInterface) {
            throw new RuntimeException(
                sprintf('%s does not implement %s.', get_class($validator), ValidatorInterface::class)
            );
        }
        return $validator;
    }

    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @return array
     */
    private function getConfig(ServiceLocatorInterface $serviceLocator)
    {
        return $serviceLocator->get('Config');
    }

    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return isset($this->getConfig($serviceLocator)['validators'][$requestedName]);
    }
}