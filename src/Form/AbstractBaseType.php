<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Abstract base class for form types.
 *
 * @template TData of ?object
 *
 * @extends AbstractType<TData>
 */
abstract class AbstractBaseType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'row_attr' => ['class' => 'form-control w-full mb-6'],
            'label_attr' => ['class' => 'label'],
            'data_class' => null,
        ]);
    }
}
