<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\Tests\Type;

use Closure;
use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Rollerworks\Bundle\MessageBusFormBundle\Tests\Mock\StubCommand;
use Rollerworks\Bundle\MessageBusFormBundle\Tests\Mock\StubQuery;
use Rollerworks\Bundle\MessageBusFormBundle\Type\MessageFormType;
use RuntimeException;
use Symfony\Component\Form\Exception\RuntimeException as FormRuntimeException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use function explode;
use function iterator_to_array;

/**
 * @internal
 */
final class MessageFormTypeTest extends TypeTestCase
{
    private $dispatchedCommand;

    protected function getTypes(): array
    {
        $messageBuses = $this->getServiceLocator([
            'command_bus1' => $this->createMessageBus([
                StubCommand::class => [
                    'stub-handler' => function (StubCommand $command) {
                        if ($command->id === 3) {
                            throw new FormRuntimeException('I have no idea how this happened.');
                        }

                        if ($command->id === 5) {
                            throw new InvalidArgumentException('Invalid id provided.');
                        }

                        if ($command->id === 6) {
                            throw new RuntimeException('What is that awful smell?');
                        }

                        if ($command->id === 42) {
                            throw new Exception('You know nothing');
                        }

                        $this->dispatchedCommand = $command;
                    },
                ],
            ]),
            'query_bus1' => $this->createMessageBus([
                StubQuery::class => [
                    'stub-handler' => function (StubQuery $command) {
                        if ($command->id === 5) {
                            return ['id' => 5, 'name' => 'Robinia'];
                        }

                        if ($command->id === 3) {
                            return ['id' => 3, 'username' => 'Iron', 'name' => 'Mask'];
                        }

                        $this->dispatchedCommand = $command;
                    },
                ],
            ]),
            'query.bus2' => $this->createMessageBus([], true),
        ]);

        return [
            new MessageFormType($messageBuses, new IdentityTranslator()),
        ];
    }

    private function getServiceLocator(array $factories)
    {
        return new class($factories) implements ContainerInterface
        {
            use ServiceLocatorTrait;
        };
    }

    private function createMessageBus(array $handlers, bool $allowNoHandlers = false): Closure
    {
        return static function () use ($handlers, $allowNoHandlers) {
            return new MessageBus([new HandleMessageMiddleware(new HandlersLocator($handlers), $allowNoHandlers)]);
        };
    }

    /** @test */
    public function it_does_not_dispatch_unless_submitted(): void
    {
        $form = $this->createFormForCommand('command_bus1');

        self::assertNull($form->getTransformationFailure());
        self::assertNull($this->dispatchedCommand);
    }

    private function createFormForCommand(string $commandBus): FormInterface
    {
        $profileContactFormType = $this->factory->createNamedBuilder('contact')
            ->add('email', TextType::class, ['required' => false])
            ->add('address', TextType::class, ['required' => false]);

        $profileFormType = $this->factory->createNamedBuilder('profile')
            ->add('name', TextType::class, ['required' => false])
            ->add($profileContactFormType);

        return $this->factory->createNamedBuilder('register_user', MessageFormType::class, null, [
            'command_bus' => $commandBus,
            'command_message_factory' => static function (array $data): StubCommand {
                return new StubCommand($data['id'], $data['username'], $data['profile'] ?? null);
            },
            'exception_mapping' => [
                FormRuntimeException::class => static function (Throwable $e) {
                    return new FormError('Root problem is here', null, [], null, $e);
                },
                InvalidArgumentException::class => static function (Throwable $e, TranslatorInterface $translator) {
                    return ['id' => new FormError($translator->trans($e->getMessage()), null, [], null, $e)];
                },
                RuntimeException::class => static function (Throwable $e) {
                    return [
                        null => [new FormError('Root problem is here2', null, [], null, $e)],
                        'username' => [new FormError('Username problem is here', null, [], null, $e)],
                    ];
                },
            ],
            'exception_fallback' => static function (Throwable $e, TranslatorInterface $translator) {
                return [
                    'profile.contact.email' => new FormError($translator->trans('Contact Email problem is here'), null, [], null, $e),
                ];
            },
        ])
            ->add('id', IntegerType::class, ['required' => false])
            ->add('username', TextType::class, ['required' => false])
            ->add($profileFormType)
            ->getForm();
    }

    /**
     * @param array<FormError[]> $expectedErrors
     *
     * @test
     * @dataProvider provideExceptions
     */
    public function it_handles_errors_thrown_during_dispatching($id, array $expectedErrors): void
    {
        $form = $this->createFormForCommand('command_bus1');
        $form->submit(['id' => $id, 'username' => 'Nero']);

        self::assertFalse($form->isValid());
        self::assertNull($form->getTransformationFailure());
        self::assertNull($this->dispatchedCommand);

        foreach ($expectedErrors as $formPath => $formErrors) {
            $formPath    = (string) $formPath;
            $currentForm = $form;

            if ($formPath !== '') {
                foreach (explode('.', $formPath) as $child) {
                    $currentForm = $currentForm->get($child);
                }
            }

            /** @var FormError $error */
            foreach ($formErrors as $error) {
                $error->setOrigin($currentForm);
            }

            self::assertEquals($formErrors, iterator_to_array($currentForm->getErrors()));
        }
    }

    public static function provideExceptions(): iterable
    {
        yield 'root form error' => [
            3,
            [
                null => [new FormError('Root problem is here', null, [], null, new FormRuntimeException('I have no idea how this happened.'))],
            ],
        ];

        yield 'sub form' => [
            5,
            [
                'id' => [new FormError('Invalid id provided.', null, [], null, new InvalidArgumentException('Invalid id provided.'))],
            ],
        ];

        yield 'sub form 2' => [
            6,
            [
                null => [new FormError('Root problem is here2', null, [], null, new RuntimeException('What is that awful smell?'))],
                'username' => [new FormError('Username problem is here', null, [], null, new RuntimeException('What is that awful smell?'))],
            ],
        ];

        yield 'fallback for form' => [
            42,
            [
                'profile.contact.email' => [new FormError('Contact Email problem is here', null, [], null, new Exception('You know nothing'))],
            ],
        ];
    }

    /**
     * @test
     */
    public function it_ignores_unmapped_exceptions_thrown_during_dispatching(): void
    {
        $form = $this->factory->createNamedBuilder('register_user', MessageFormType::class, null, [
            'command_bus' => 'command_bus1',
            'command_message_factory' => static function (array $data): StubCommand {
                return new StubCommand($data['id'], $data['username'], $data['profile'] ?? null);
            },
            'exception_mapping' => [
                FormRuntimeException::class => static function (Throwable $e) {
                    return new FormError('Root problem is here', null, [], null, $e);
                },
            ],
        ])
            ->add('id', IntegerType::class, ['required' => false])
            ->add('username', TextType::class, ['required' => false])
            ->getForm();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You know nothing');

        $form->submit(['id' => 42, 'username' => 'Nero']);
    }

    /** @test */
    public function it_dispatches_a_command(): void
    {
        $form = $this->createFormForCommand('command_bus1');
        $form->submit(['id' => '8', 'username' => 'Nero']);

        self::assertTrue($form->isValid());
        self::assertNull($form->getTransformationFailure());
        self::assertEquals(new StubCommand(8, 'Nero', [
            'name' => null,
            'contact' => [
                'email' => null,
                'address' => null,
            ],
        ]), $this->dispatchedCommand);
    }

    /** @test */
    public function it_handles_a_query(): void
    {
        $form = $this->factory->createNamedBuilder('register_user', MessageFormType::class, ['id' => 5], [
            'command_bus' => 'command_bus1',
            'query_bus' => 'query_bus1',
            'command_message_factory' => static function (array $data): StubCommand {
                return new StubCommand($data['id'], $data['username'], $data['profile'] ?? null);
            },
            'query_message_factory' => static function (array $data): StubQuery {
                return new StubQuery($data['id']);
            },
        ])
            ->add('id', IntegerType::class, ['required' => false])
            ->add('username', TextType::class, ['required' => false])
            ->add('name', TextType::class, ['required' => false])
            ->getForm();

        self::assertNull($form->getTransformationFailure());

        self::assertEquals(5, $form->get('id')->getData());
        self::assertEquals('Robinia', $form->get('name')->getData());
        self::assertNull($form->get('username')->getData());
    }

    /** @test */
    public function it_handles_a_query_and_transforms(): void
    {
        $form = $this->factory->createNamedBuilder('register_user', MessageFormType::class, ['id' => 3], [
            'command_bus' => 'command_bus1',
            'query_bus' => 'query_bus1',
            'command_message_factory' => static function (array $data): StubCommand {
                return new StubCommand($data['id'], $data['username'], $data['profile'] ?? null);
            },
            'query_message_factory' => static function (array $data): StubQuery {
                return new StubQuery($data['id']);
            },
            'query_result_transformer' => static function (array $data): array {
                return ['id' => $data['id'] + 1, 'username' => $data['name'], 'name' => $data['username']];
            },
        ])
            ->add('id', IntegerType::class, ['required' => false])
            ->add('username', TextType::class, ['required' => false])
            ->add('name', TextType::class, ['required' => false])
            ->getForm();

        self::assertNull($form->getTransformationFailure());

        self::assertEquals(4, $form->get('id')->getData());
        self::assertEquals('Iron', $form->get('name')->getData());
        self::assertEquals('Mask', $form->get('username')->getData());
    }
}
