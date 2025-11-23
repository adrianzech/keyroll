<?php

declare(strict_types=1);

namespace App\DataTable;

use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Query\DoctrineOrmProxyQueryInterface;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Filter\Type\CallbackFilterType;
use Kreyu\Bundle\DataTableBundle\Pagination\PaginationData;
use Kreyu\Bundle\DataTableBundle\Query\ProxyQueryInterface;
use Kreyu\Bundle\DataTableBundle\Type\AbstractDataTableType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserDataTableType extends AbstractDataTableType
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TranslatorInterface $translator,
        private readonly DataTableConfigurator $dataTableConfigurator,
    ) {
    }

    public function buildDataTable(DataTableBuilderInterface $builder, array $options): void
    {
        $this->configureColumns($builder);
        $this->configureFilters($builder);
        $this->configureSearch($builder);
        $this->dataTableConfigurator->addDefaultExporters($builder);
        $this->setDefaultPagination($builder);
        $this->configureAdminActions($builder);
    }

    private function configureColumns(DataTableBuilderInterface $builder): void
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
                    'formatter' => function (mixed $categories): string {
                        if (!is_array($categories)) {
                            return '';
                        }

                        return implode(', ', array_map(
                            static fn (object $category): string => method_exists($category, 'getName') ? (string) $category->getName() : '',
                            $categories,
                        ));
                    },
                ],
                'sort' => false,
                'getter' => static fn ($user): array => $user->getCategories()->toArray(),
                'block_prefix' => 'category_badge',
            ])
            ->addColumn('primaryRole', TextColumnType::class, [
                'label' => 'entity.user.label.role',
                'export' => [
                    'label' => $this->translator->trans('entity.user.label.role', [], 'messages'),
                    'formatter' => function (?string $role): string {
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
            ]);
    }

    private function configureFilters(DataTableBuilderInterface $builder): void
    {
        $builder
            ->addFilter('name', StringFilterType::class, [
                'label' => 'entity.user.label.name',
            ])
            ->addFilter('email', StringFilterType::class, [
                'label' => 'entity.user.label.email',
            ]);

        $this->configureCategoryFilter($builder);
        $this->configureRoleFilter($builder);
    }

    private function configureCategoryFilter(DataTableBuilderInterface $builder): void
    {
        $builder->addFilter('categories', CallbackFilterType::class, [
            'label' => 'entity.user.label.categories',
            'form_type' => EntityType::class,
            'form_options' => [
                'class' => 'App\Entity\Category',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ],
            'active_filter_formatter' => static fn ($data): string => implode(', ', array_map(
                static fn (object $category): string => method_exists($category, 'getName') ? (string) $category->getName() : '',
                self::extractEntities($data, 'App\Entity\Category'),
            )),
            'callback' => function (ProxyQueryInterface $query, $data): void {
                if (!$query instanceof DoctrineOrmProxyQueryInterface) {
                    return;
                }

                $categories = self::extractEntities($data, 'App\Entity\Category');

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
        ]);
    }

    private function configureRoleFilter(DataTableBuilderInterface $builder): void
    {
        $builder->addFilter('primaryRole', CallbackFilterType::class, [
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
            'active_filter_formatter' => fn ($data): string => $data->hasValue()
                ? $this->translator->trans(match ((string) $data->getValue()) {
                    'ROLE_ADMIN' => 'entity.user.label.admin',
                    default => 'entity.user.label.user',
                }, [], 'messages')
                : '',
            'callback' => function (ProxyQueryInterface $query, $data): void {
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
        ]);
    }

    private function configureSearch(DataTableBuilderInterface $builder): void
    {
        $builder->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
            if (!$query instanceof DoctrineOrmProxyQueryInterface) {
                return;
            }

            $query
                ->leftJoin('user.categories', 'search_category')
                ->andWhere('user.name LIKE :search OR user.email LIKE :search OR search_category.name LIKE :search OR user.roles LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->distinct();
        });
    }

    private function setDefaultPagination(DataTableBuilderInterface $builder): void
    {
        $builder->setDefaultPaginationData(
            new PaginationData(
                page: 1,
                perPage: 10,
            )
        );
    }

    private function configureAdminActions(DataTableBuilderInterface $builder): void
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $this->dataTableConfigurator->addAdminActions(
            builder: $builder,
            editRoute: 'app_user_edit',
            deleteRoute: 'app_user_delete',
            deleteTranslationKey: 'entity.user.dialog.delete_confirm',
            idAccessor: static fn ($user) => $user->getId(),
            nameAccessor: static fn ($user) => $user->getName(),
        );
    }

    /**
     * @return array<int, object>
     */
    private static function extractEntities(mixed $data, string $expectedClass): array
    {
        if (!$data->hasValue()) {
            return [];
        }

        $rawEntities = $data->getValue();
        $entities = match (true) {
            is_array($rawEntities) => $rawEntities,
            is_iterable($rawEntities) => iterator_to_array($rawEntities),
            default => [$rawEntities],
        };

        return array_values(array_filter(
            $entities,
            static fn (mixed $entity): bool => $entity instanceof $expectedClass,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => 'App\Entity\User',
        ]);
    }
}
