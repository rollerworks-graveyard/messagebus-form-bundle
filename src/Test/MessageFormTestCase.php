<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\Test;

use Closure;
use Psr\Container\ContainerInterface;
use Rollerworks\Bundle\MessageBusFormBundle\Type\MessageFormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use function ctype_digit;
use function explode;
use function is_callable;

abstract class MessageFormTestCase extends TypeTestCase
{
    protected $dispatchedCommand;

    /** @var callable|null */
    protected $commandHandler;

    /** @var callable|null */
    protected $queryHandler;

    protected function getMessageType(): MessageFormType
    {
        $messageBuses = $this->getServiceLocator([
            'command_bus' => $this->createMessageBus([
                static::getCommandName() => [
                    'handler' => function (object $command) {
                        if (is_callable($this->commandHandler)) {
                            ($this->commandHandler)($command);
                            $this->dispatchedCommand = $command;

                            return;
                        }

                        $this->fail('The "commandHandler" property must be set with valid callable.');
                    },
                ],
            ]),
            'query_bus' => $this->createMessageBus([
                static::getQueryName() => [
                    'handler' => function (object $command) {
                        if (is_callable($this->queryHandler)) {
                            return ($this->queryHandler)($command);
                        }

                        $this->fail('The "queryHandler" property must be set with valid callable.');
                    },
                ],
            ]),
        ]);

        return new MessageFormType($messageBuses, new IdentityTranslator());
    }

    protected function getServiceLocator(array $factories)
    {
        return new class($factories) implements ContainerInterface
        {
            use ServiceLocatorTrait;
        };
    }

    protected function createMessageBus(array $handlers): Closure
    {
        return static function () use ($handlers) {
            return new MessageBus([new HandleMessageMiddleware(new HandlersLocator($handlers), false)]);
        };
    }

    abstract protected static function getCommandName(): string;

    protected static function getQueryName(): string
    {
        return 'nope';
    }

    /**
     * @param array<string|null,FormError[]> $expectedErrors
     */
    protected function assertFormHasErrors(FormInterface $form, iterable $expectedErrors): void
    {
        self::assertFalse($form->isValid());
        self::assertNull($form->getTransformationFailure());
        self::assertNull($this->dispatchedCommand);

        foreach ($expectedErrors as $formPath => $formErrors) {
            $formPath    = (string) $formPath;
            $currentForm = $form;

            if ($formPath !== '' && ! ctype_digit($formPath)) {
                foreach (explode('.', $formPath) as $child) {
                    $currentForm = $currentForm->get($child);
                }
            }

            self::assertThat($currentForm->getErrors(), new IsFormErrorsEqual($formErrors));
        }
    }
}
