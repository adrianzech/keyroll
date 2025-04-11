<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Defines the form used for editing account settings (email, password).
 *
 * @extends AbstractType<User>
 */
class AccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'empty_data' => '',
                'required' => true,
                'constraints' => [
                    new Email(['message' => 'auth.email_invalid']),
                ],
                'label' => false,
            ])
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'auth.password_min_length',
                    ]),
                ],
                'label' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'settings.account.new_password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'settings.account.confirm_new_password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'auth.password_mismatch',
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'minMessage' => 'auth.password_min_length',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
