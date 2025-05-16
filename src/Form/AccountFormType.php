<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Validator\ValidAccountUpdate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
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
                    new NotBlank(),
                    new Email(['message' => 'auth.email_invalid']),
                ],
                'label' => false,
            ])
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'settings.account.current_password',
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'settings.account.new_password',
                    'attr' => ['autocomplete' => 'new-password'],
                    'constraints' => [
                        new Length([
                            'min' => 8,
                            'minMessage' => 'auth.password_min_length',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'settings.account.confirm_new_password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'auth.password_mismatch',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => User::class,
            'constraints' => [
                new ValidAccountUpdate(),
            ],
        ]);
    }
}
