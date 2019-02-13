<?php

declare(strict_types=1);

namespace Rollerworks\Bundle\MessageBusFormBundle\Type;

use Psr\Container\ContainerInterface;
use Rollerworks\Bundle\MessageBusFormBundle\QueryBus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use function explode;
use function get_class;
use function is_array;

final class MessageFormType extends AbstractType
{
    /** @var ContainerInterface */
    private $messageBuses;

    /** @var TranslatorInterface */
    private $translator;

    public function __construct(ContainerInterface $messageBuses, TranslatorInterface $translator)
    {
        $this->messageBuses = $messageBuses;
        $this->translator = $translator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['command_message_factory', 'command_bus']);
        $resolver->setDefault('exception_mapping', []);
        $resolver->setDefault('exception_fallback', null);

        $resolver->setDefault('query_bus', null);
        $resolver->setDefault('query_message_factory', null);
        $resolver->setDefault('query_result_transformer', null);

        $resolver->setAllowedTypes('command_message_factory', ['callable']);
        $resolver->setAllowedTypes('command_bus', ['string']);
        $resolver->setAllowedTypes('exception_mapping', ['callable[]']);
        $resolver->setAllowedTypes('exception_fallback', ['callable', 'null']);

        $resolver->setAllowedTypes('query_bus', ['string', 'null']);
        $resolver->setAllowedTypes('query_message_factory', ['callable', 'null']);
        $resolver->setAllowedTypes('query_result_transformer', ['callable', 'null']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (isset($options['query_message_factory'])) {
            if (! isset($options['query_bus'])) {
                throw new InvalidConfigurationException('The "query_bus" option must be set when "query_message_factory" is used.');
            }

            $queryBus = new QueryBus($this->messageBuses->get($options['query_bus']));

            // Caution: This should be always executed as first!
            $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($queryBus, $options) {
                $data = $queryBus->query($options['query_message_factory']($event->getData()));

                if (isset($options['query_result_transformer'])) {
                    $data = $options['query_result_transformer']($data);
                }

                $event->setData($data);
            }, 1024);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();

            if ($form->getTransformationFailure() === null && $form->isValid()) {
                $this->dispatchCommand(
                    $this->messageBuses->get($options['command_bus']),
                    $options['command_message_factory']($form->getData()),
                    $form,
                    $options['exception_mapping'],
                    $options['exception_fallback']
                );
            }
        }, -1050); // After everything, the TransformationFailureExtension has a priority of 1024
    }

    private function dispatchCommand(MessageBusInterface $bus, object $command, FormInterface $form, array $exceptionMapping, ?callable $exceptionFallback): void
    {
        try {
            $bus->dispatch($command);
        } catch (Throwable $e) {
            $exceptionName = get_class($e);

            if (isset($exceptionMapping[$exceptionName])) {
                $errors = $exceptionMapping[$exceptionName]($e, $this->translator);
            } elseif ($exceptionFallback !== null) {
                $errors = $exceptionFallback($e, $this->translator);
            } else {
                throw $e;
            }

            $this->mapErrors($errors, $form);
        }
    }

    private function mapErrors($errors, FormInterface $form): void
    {
        if (! is_array($errors)) {
            $errors = [null => [$errors]];
        }

        foreach ($errors as $formPath => $formErrors) {
            if (! is_array($formErrors)) {
                $formErrors = [$formErrors];
            }

            $formPath = (string) $formPath;

            if ($formPath !== '') {
                foreach (explode('.', $formPath) as $child) {
                    $form = $form->get($child);
                }
            }

            foreach ($formErrors as $error) {
                $form->addError($error);
            }
        }
    }
}
