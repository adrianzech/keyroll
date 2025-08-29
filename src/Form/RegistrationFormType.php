<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'attr' => [
                    'placeholder' => 'auth.name_placeholder',
                    'autofocus' => true,
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'attr' => [
                    'placeholder' => 'auth.email_placeholder',
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'auth.register_need_to_agree'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'label' => 'label.password',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'auth.password_placeholder',
                ],
                'constraints' => [
                    new NotBlank(message: 'auth.enter_password'),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'auth.password_min_length',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
