<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Host;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractType<Host>
 */
class HostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'host.name',
                'attr' => [
                    'placeholder' => 'host.name_placeholder',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'host.name_required',
                    ]),
                ],
            ])
            ->add('hostname', TextType::class, [
                'label' => 'host.hostname',
                'attr' => [
                    'placeholder' => 'host.hostname_placeholder',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'host.hostname_required',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9.-]+$/',
                        'message' => 'host.hostname_invalid_format',
                    ]),
                ],
                'help' => 'host.hostname_help',
            ])
            ->add('port', IntegerType::class, [
                'label' => 'host.port',
                'attr' => [
                    'placeholder' => 'host.default_port',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'host.port_required',
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 65535,
                        'notInRangeMessage' => 'host.port_invalid_range',
                    ]),
                ],
                'data' => 22,
            ])
            ->add('username', TextType::class, [
                'label' => 'host.username',
                'attr' => [
                    'placeholder' => 'host.default_user',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'host.username_required',
                    ]),
                ],
                'data' => 'host.default_user',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Host::class,
        ]);
    }
}
