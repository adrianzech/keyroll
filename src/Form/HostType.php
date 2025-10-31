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
                'label' => 'entity.host.label.name',
                'attr' => ['placeholder' => 'entity.host.placeholder.name'],
                'constraints' => [
                    new NotBlank(message: 'entity.host.validation.name.required'),
                    new Regex(pattern: '/^[a-zA-Z0-9.\-\s]+$/', message: 'entity.host.validation.name.invalid_format'),
                ],
            ])
            ->add('hostname', TextType::class, [
                'label' => 'entity.host.label.hostname',
                'attr' => ['placeholder' => 'entity.host.placeholder.hostname'],
                'constraints' => [
                    new NotBlank(message: 'entity.host.validation.hostname.required'),
                    new Regex(pattern: '/^[a-zA-Z0-9.-]+$/', message: 'entity.host.validation.hostname.invalid_format'),
                ],
            ])
            ->add('port', IntegerType::class, [
                'label' => 'entity.host.label.port',
                'attr' => [
                    'placeholder' => 'entity.host.placeholder.port',
                    'min' => 1,
                    'max' => 65535,
                ],
                'constraints' => [
                    new NotBlank(message: 'entity.host.validation.port.required'),
                    new Range(notInRangeMessage: 'entity.host.validation.port.invalid_range', min: 1, max: 65535),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'entity.host.label.username',
                'attr' => ['placeholder' => 'entity.host.placeholder.username'],
                'constraints' => [
                    new NotBlank(message: 'entity.host.validation.username.required'),
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
