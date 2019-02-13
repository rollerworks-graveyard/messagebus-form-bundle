<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle;

use Rollerworks\Bundle\MessageBusFormBundle\DepedencyInjection\Compiler\ServiceBusFinderPass;
use Rollerworks\Bundle\MessageBusFormBundle\DepedencyInjection\DependencyExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class RollerworksMessageBusFormBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ServiceBusFinderPass());
    }

    protected function getContainerExtensionClass(): string
    {
        return DependencyExtension::class;
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ?? $this->extension = new DependencyExtension();
    }
}
