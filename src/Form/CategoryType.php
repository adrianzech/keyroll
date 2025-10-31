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
                'label' => 'entity.category.label.name',
                'attr' => [
                    'placeholder' => 'entity.category.placeholder.name',
                ],
            ])
            ->add('hosts', EntityType::class, [
                'class' => Host::class,
                'choice_label' => function (Host $host) {
                    return $host->getName() . ' (' . $host->getHostname() . ')';
                },
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'entity.category.label.assigned_hosts',
                'attr' => [
                    'class' => 'hidden-symfony-entity-selector',
                    'data-entity-selector-target' => 'symfonyField',
                ],
                'by_reference' => false,
            ])
            ->add('users', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'entity.category.label.assigned_users',
                'attr' => [
                    'class' => 'hidden-symfony-entity-selector',
                    'data-entity-selector-target' => 'symfonyField',
                ],
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
