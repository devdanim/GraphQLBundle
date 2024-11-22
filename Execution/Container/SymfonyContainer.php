<?php
/**
 * This file is a part of PhpStorm project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 9/23/16 10:08 PM
 */

namespace Youshido\GraphQLBundle\Execution\Container;


use Youshido\GraphQL\Execution\Container\ContainerInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;

class SymfonyContainer implements ContainerInterface
{
    protected $container;

    public function __construct(PsrContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get($id)
    {
        return $this->container->get($id);
    }

    public function set($id, $value)
    {
        $this->container->set($id, $value);
        return $this;
    }

    public function remove($id)
    {
        throw new \RuntimeException('Remove method is not available for Symfony container');
    }

    public function has($id)
    {
        return $this->container->has($id);
    }

    public function initialized($id)
    {
        return $this->container->initialized($id);
    }

    public function setParameter($name, $value)
    {
        $this->container->setParameter($name, $value);
        return $this;
    }

    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    public function hasParameter($name)
    {
        return $this->container->hasParameter($name);
    }

    /**
     * Exists temporarily for ContainerAwareField that is to be removed in 1.5
     * @return mixed
     */
    public function getSymfonyContainer()
    {
        return $this->container;
    }

}