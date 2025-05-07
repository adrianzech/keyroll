<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Host;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Category>
 */
class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'category.form.name',
                'label_attr' => ['class' => 'label'],
                'attr' => [
                    'class' => 'input input-bordered w-full',
                    'placeholder' => 'category.name_placeholder',
                ],
                'row_attr' => ['class' => 'form-control mb-4'],
            ]);

        // Field for selecting Hosts
        $builder->add('hosts', EntityType::class, [
            'class' => Host::class,
            'choice_label' => function (Host $host) {
                return $host->getName() . ' (' . $host->getHostname() . ')';
            },
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'label' => 'category.form.associated_hosts',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'class' => 'hidden-symfony-entity-selector',
                'data-entity-selector-target' => 'symfonyField',
            ],
            'by_reference' => false,
            'row_attr' => ['class' => 'form-control entity-selector-symfony-row'],
        ]);

        // Field for selecting Users
        $builder->add('users', EntityType::class, [
            'class' => User::class,
            'choice_label' => 'email',
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'label' => 'category.form.associated_users',
            'label_attr' => ['class' => 'label'],
            'attr' => [
                'class' => 'hidden-symfony-entity-selector',
                'data-entity-selector-target' => 'symfonyField',
            ],
            'by_reference' => false,
            'row_attr' => ['class' => 'form-control entity-selector-symfony-row'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
