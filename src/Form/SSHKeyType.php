<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SSHKey;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractType<SSHKey>
 */
class SSHKeyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'entity.ssh_key.label.name',
                'attr' => ['placeholder' => 'entity.ssh_key.placeholder.name'],
                'constraints' => [
                    new NotBlank(message: 'entity.ssh_key.validation.name.required'),
                ],
            ])
            ->add('publicKey', TextareaType::class, [
                'label' => 'entity.ssh_key.label.public_key',
                'attr' => [
                    'placeholder' => 'entity.ssh_key.placeholder.public_key',
                    'rows' => 5,
                ],
                'constraints' => [
                    new NotBlank(message: 'entity.ssh_key.validation.public_key.required'),
                    new Regex(
                        pattern: '/^ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521/',
                        message: 'entity.ssh_key.validation.public_key.invalid_format',
                    ),
                ],
            ]);

        // Add user selection field only for admins
        if ($options['is_admin']) {
            $builder->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'label' => 'entity.ssh_key.label.user',
                'placeholder' => 'entity.ssh_key.placeholder.select_user',
                'constraints' => [
                    new NotBlank(message: 'entity.ssh_key.validation.user.required'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => SSHKey::class,
            'is_admin' => false,
        ]);
    }
}
