<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TotpSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'two_fa.label.code',
            'required' => true,
            'attr' => [
                'autocomplete' => 'one-time-code',
                'inputmode' => 'numeric',
                'placeholder' => 'two_fa.placeholder.code',
            ],
            'constraints' => [
                new NotBlank(message: 'two_fa.validation.code_required'),
                new Length(
                    min: 6,
                    max: 8,
                    minMessage: 'two_fa.validation.code_length',
                    maxMessage: 'two_fa.validation.code_length',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
