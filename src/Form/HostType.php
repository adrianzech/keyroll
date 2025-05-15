<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Host;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractBaseType<Host>
 */
class HostType extends AbstractBaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'host.label.name',
                'attr' => ['placeholder' => 'host.placeholder.name'],
                'constraints' => [
                    new NotBlank(['message' => 'host.name_required']),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9.\-\s]+$/',
                        'message' => 'host.name_invalid_format',
                    ]),
                ],
            ])
            ->add('hostname', TextType::class, [
                'label' => 'host.label.hostname',
                'attr' => ['placeholder' => 'host.placeholder.hostname'],
                'constraints' => [
                    new NotBlank(['message' => 'host.hostname_required']),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9.-]+$/',
                        'message' => 'host.hostname_invalid_format',
                    ]),
                ],
            ])
            ->add('port', IntegerType::class, [
                'label' => 'host.label.port',
                'attr' => [
                    'placeholder' => 'host.placeholder.port',
                    'min' => 1,
                    'max' => 65535,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'host.port_required']),
                    new Range([
                        'min' => 1,
                        'max' => 65535,
                        'notInRangeMessage' => 'host.port_invalid_range',
                    ]),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'host.label.username',
                'attr' => ['placeholder' => 'host.placeholder.username'],
                'constraints' => [
                    new NotBlank(['message' => 'host.username_required']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => Host::class,
        ]);
    }
}
