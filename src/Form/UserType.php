<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Form\DataTransformer\RoleToStringTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
class UserType extends AbstractType
{
    public function __construct(
        private readonly RoleToStringTransformer $roleTransformer
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name',
                'attr' => [
                    'placeholder' => 'auth.name_placeholder',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
                'attr' => [
                    'placeholder' => 'auth.email_placeholder',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'required' => false,
                'mapped' => false,
                'label' => 'label.password',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'auth.password_placeholder'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'auth.enter_password',
                        'groups' => ['registration'],
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'auth.password_min_length',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                        'groups' => ['Default', 'registration'],
                    ]),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'label.role',
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => false,
                'expanded' => false,
                'required' => true,
                'placeholder' => false,
            ]);

        $builder->get('roles')->addModelTransformer($this->roleTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
