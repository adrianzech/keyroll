<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SSHKey;
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
                'label' => 'key.name',
                'attr' => [
                    'placeholder' => 'key.name_placeholder',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'key.name_required',
                    ]),
                ],
            ])
            ->add('publicKey', TextareaType::class, [
                'label' => 'key.public_key',
                'attr' => [
                    'placeholder' => 'key.public_key_placeholder',
                    'rows' => 5,
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'key.public_key_required',
                    ]),
                    new Regex([
                        'pattern' => '/^ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521/',
                        'message' => 'key.public_key_invalid_format',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SSHKey::class,
        ]);
    }
}
