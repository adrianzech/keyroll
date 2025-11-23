<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Category;
use App\Entity\User;
use Kreyu\Bundle\DataTableBundle\Action\Type\ButtonActionType;
use Kreyu\Bundle\DataTableBundle\Action\Type\FormActionType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Query\DoctrineOrmProxyQueryInterface;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\CsvExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\OdsExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\XlsxExporterType;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Filter\FilterData;
use Kreyu\Bundle\DataTableBundle\Filter\Type\CallbackFilterType;
use Kreyu\Bundle\DataTableBundle\Pagination\PaginationData;
use Kreyu\Bundle\DataTableBundle\Query\ProxyQueryInterface;
use Kreyu\Bundle\DataTableBundle\Type\AbstractDataTableType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserDataTableType extends AbstractDataTableType
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildDataTable(DataTableBuilderInterface $builder, array $options): void
    {
        $builder
            ->addColumn('name', TextColumnType::class, [
                'label' => 'entity.user.label.name',
                'export' => [
                    'label' => $this->translator->trans('entity.user.label.name', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('email', TextColumnType::class, [
                'label' => 'entity.user.label.email',
                'export' => [
                    'label' => $this->translator->trans('entity.user.label.email', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('categories', TextColumnType::class, [
                'label' => 'entity.user.label.categories',
                'export' => [
                    'label' => $this->translator->trans('entity.user.label.categories', [], 'messages'),
                    'formatter' => function (mixed $categories, mixed ...$context): string {
                        if (!is_array($categories)) {
                            return '';
                        }

                        return implode(', ', array_map(
                            static fn (Category $category): string => $category->getName(),
                            $categories,
                        ));
                    },
                ],
                'sort' => false,
                'getter' => static fn (User $user, mixed ...$context): array => $user->getCategories()->toArray(),
                'block_prefix' => 'category_badge',
            ])
            ->addColumn('primaryRole', TextColumnType::class, [
                'label' => 'entity.user.label.role',
                'export' => [
                    'label' => $this->translator->trans('entity.user.label.role', [], 'messages'),
                    'formatter' => function (?string $role, mixed ...$context): string {
                        return match ($role) {
                            'ROLE_ADMIN' => $this->translator->trans('entity.user.label.admin', [], 'messages'),
                            'ROLE_USER' => $this->translator->trans('entity.user.label.user', [], 'messages'),
                            null => '',
                            default => ucfirst(strtolower(str_replace('ROLE_', '', $role))),
                        };
                    },
                    'value_translation_domain' => false,
                ],
                'sort' => false,
                'block_prefix' => 'role_badge',
            ])
            ->addFilter('name', StringFilterType::class, [
                'label' => 'entity.user.label.name',
            ])
            ->addFilter('email', StringFilterType::class, [
                'label' => 'entity.user.label.email',
            ])
            ->addFilter('categories', CallbackFilterType::class, [
                'label' => 'entity.user.label.categories',
                'form_type' => EntityType::class,
                'form_options' => [
                    'class' => Category::class,
                    'choice_label' => 'name',
                    'multiple' => true,
                    'required' => false,
                ],
                'active_filter_formatter' => static fn (FilterData $data): string => implode(', ', array_map(
                    static fn (Category $category): string => $category->getName(),
                    array_filter(
                        ($data->getValue() instanceof \Traversable ? iterator_to_array($data->getValue()) : (array) $data->getValue()),
                        static fn (mixed $category): bool => $category instanceof Category,
                    ),
                )),
                'callback' => function (ProxyQueryInterface $query, FilterData $data): void {
                    if (!$data->hasValue() || !$query instanceof DoctrineOrmProxyQueryInterface) {
                        return;
                    }

                    $rawCategories = $data->getValue();
                    $categories = array_filter(
                        $rawCategories instanceof \Traversable ? iterator_to_array($rawCategories) : (array) $rawCategories,
                        static fn (mixed $category): bool => $category instanceof Category,
                    );

                    if ($categories === []) {
                        return;
                    }

                    $queryBuilder = $query->getQueryBuilder();
                    $rootAlias = current($queryBuilder->getRootAliases());

                    $queryBuilder
                        ->leftJoin($rootAlias . '.categories', 'filter_category')
                        ->andWhere('filter_category IN (:categories)')
                        ->setParameter('categories', $categories)
                        ->distinct();
                },
            ])
            ->addFilter('primaryRole', CallbackFilterType::class, [
                'label' => 'entity.user.label.role',
                'form_type' => ChoiceType::class,
                'form_options' => [
                    'choices' => [
                        'entity.user.label.admin' => 'ROLE_ADMIN',
                        'entity.user.label.user' => 'ROLE_USER',
                    ],
                    'placeholder' => '',
                    'required' => false,
                ],
                'active_filter_formatter' => fn (FilterData $data): string => $data->hasValue()
                    ? $this->translator->trans(match ((string) $data->getValue()) {
                        'ROLE_ADMIN' => 'entity.user.label.admin',
                        default => 'entity.user.label.user',
                    }, [], 'messages')
                    : '',
                'callback' => function (ProxyQueryInterface $query, FilterData $data): void {
                    if (!$data->hasValue() || !$query instanceof DoctrineOrmProxyQueryInterface) {
                        return;
                    }

                    $queryBuilder = $query->getQueryBuilder();
                    $rootAlias = current($queryBuilder->getRootAliases());

                    $role = (string) $data->getValue();

                    $queryBuilder
                        ->andWhere($rootAlias . '.roles LIKE :rolePattern')
                        ->setParameter('rolePattern', '%"' . $role . '"%');
                },
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                if (!$query instanceof DoctrineOrmProxyQueryInterface) {
                    return;
                }

                $query
                    ->leftJoin('user.categories', 'search_category')
                    ->andWhere('user.name LIKE :search OR user.email LIKE :search OR search_category.name LIKE :search OR user.roles LIKE :search')
                    ->setParameter('search', '%' . $search . '%')
                    ->distinct();
            })
            ->addExporter('csv', CsvExporterType::class, [
                'label' => 'data_table.export.csv',
            ])
            ->addExporter('ods', OdsExporterType::class, [
                'label' => 'data_table.export.ods',
            ])
            ->addExporter('xlsx', XlsxExporterType::class, [
                'label' => 'data_table.export.xlsx',
            ])
            ->setDefaultPaginationData(
                new PaginationData(
                    page: 1,
                    perPage: 10,
                )
            );

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->addRowAction('edit', ButtonActionType::class, [
                    'href' => fn (User $user) => $this->urlGenerator->generate('app_user_edit', [
                        'id' => $user->getId(),
                    ]),
                    'label' => 'common.button.edit',
                    'variant' => 'light',
                ])
                ->addRowAction('delete', FormActionType::class, [
                    'action' => fn (User $user) => $this->urlGenerator->generate('app_user_delete', [
                        'id' => $user->getId(),
                    ]),
                    'method' => 'POST',
                    'label' => 'common.button.delete',
                    'variant' => 'danger',
                    'confirmation' => fn (User $user) => [
                        'type' => 'danger',
                        'translation_domain' => 'messages',
                        'label_title' => 'common.dialog.delete_title',
                        'label_description' => $this->translator->trans('entity.user.dialog.delete_confirm', [
                            '%name%' => $user->getName(),
                        ], 'messages'),
                        'label_confirm' => 'common.button.delete',
                        'label_cancel' => 'common.button.cancel',
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
