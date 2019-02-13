<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\DepedencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ServiceBusFinderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->has('rollerworks_messagebus_form.form_type.message')) {
            return;
        }

        $def = $container->findDefinition('rollerworks_messagebus_form.form_type.message');
        $def->replaceArgument(0, ServiceLocatorTagPass::register($container, $this->findTaggedServices('messenger.bus', $container)));
    }

    /**
     * @return Reference[]
     */
    private function findTaggedServices(string $tagName, ContainerBuilder $container): array
    {
        $services = [];

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            $services[$serviceId] = new Reference($serviceId);
        }

        return $services;
    }
}
