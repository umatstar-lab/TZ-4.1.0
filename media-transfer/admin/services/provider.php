<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
return new class implements ServiceProviderInterface {
 public function register(Container $container) {
  $container->registerServiceProvider(new MVCFactory('\\Umatstar\\Component\\TzMediaTransfer'));
  $container->registerServiceProvider(new ComponentDispatcherFactory('\\Umatstar\\Component\\TzMediaTransfer'));
  $container->set(ComponentInterface::class, function(Container $c) {
   $component=new MVCComponent($c->get(ComponentDispatcherFactoryInterface::class));
   $component->setMVCFactory($c->get(MVCFactoryInterface::class));return $component;
  });
 }
};
