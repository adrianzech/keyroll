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
        private readonly RoleToStringTransformer $roleTransformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'common.label.name',
                'attr' => [
                    'placeholder' => 'auth.field.name_placeholder',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'common.label.email',
                'attr' => [
                    'placeholder' => 'auth.field.email_placeholder',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'required' => false,
                'mapped' => false,
                'label' => 'common.label.password',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'auth.field.password_placeholder',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'auth.validation.password.required',
                        groups: ['registration'],
                    ),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'auth.validation.password.min_length',
                        groups: ['Default', 'registration'],
                    ),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'common.label.role',
                'choices' => [
                    'entity.user.label.user' => 'ROLE_USER',
                    'entity.user.label.admin' => 'ROLE_ADMIN',
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
