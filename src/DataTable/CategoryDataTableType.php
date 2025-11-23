<?php

declare(strict_types=1);

namespace App\DataTable;

use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\DateFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Filter\Type\CallbackFilterType;
use Kreyu\Bundle\DataTableBundle\Pagination\PaginationData;
use Kreyu\Bundle\DataTableBundle\Type\AbstractDataTableType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CategoryDataTableType extends AbstractDataTableType
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
        $this->configureExporters($builder);
        $this->setDefaultPagination($builder);
        $this->configureAdminActions($builder);
    }

    private function configureColumns(DataTableBuilderInterface $builder): void
    {
        $builder
            ->addColumn('name', TextColumnType::class, [
                'label' => 'entity.category.label.name',
                'export' => [
                    'label' => $this->translator->trans('entity.category.label.name', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('hostsCount', TextColumnType::class, [
                'label' => 'entity.category.label.assigned_hosts_count',
                'export' => [
                    'label' => $this->translator->trans('entity.category.label.assigned_hosts_count', [], 'messages'),
                ],
                'sort' => false,
                'getter' => fn ($category) => $category->getHosts()->count(),
            ])
            ->addColumn('usersCount', TextColumnType::class, [
                'label' => 'entity.category.label.assigned_users_count',
                'export' => [
                    'label' => $this->translator->trans('entity.category.label.assigned_users_count', [], 'messages'),
                ],
                'sort' => false,
                'getter' => fn ($category) => $category->getUsers()->count(),
            ])
            ->addColumn('createdAt', TextColumnType::class, [
                'label' => 'common.label.created_at',
                'export' => [
                    'label' => $this->translator->trans('common.label.created_at', [], 'messages'),
                ],
                'sort' => true,
                'block_prefix' => 'time_ago',
            ]);
    }

    private function configureFilters(DataTableBuilderInterface $builder): void
    {
        $builder->addFilter('name', StringFilterType::class, [
            'label' => 'entity.category.label.name',
        ]);

        $this->addRelationFilter(
            builder: $builder,
            name: 'hosts',
            label: 'entity.category.label.assigned_hosts',
            association: 'category.hosts',
            alias: 'filter_host',
            parameter: 'hosts',
            entityClass: 'App\Entity\Host',
        );

        $this->addRelationFilter(
            builder: $builder,
            name: 'users',
            label: 'entity.category.label.assigned_users',
            association: 'category.users',
            alias: 'filter_user',
            parameter: 'users',
            entityClass: 'App\Entity\User',
        );

        $builder->addFilter('createdAt', DateFilterType::class, [
            'label' => 'common.label.created_at',
            'operator_selectable' => true,
        ]);
    }

    private function configureSearch(DataTableBuilderInterface $builder): void
    {
        $builder->setSearchHandler(function ($query, string $search): void {
            /* @noinspection PhpUndefinedMethodInspection */
            $query
                ->leftJoin('category.hosts', 'search_host')
                ->leftJoin('category.users', 'search_user')
                ->andWhere('category.name LIKE :search OR search_host.name LIKE :search OR search_user.name LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->distinct();
        });
    }

    private function configureExporters(DataTableBuilderInterface $builder): void
    {
        $this->dataTableConfigurator->addDefaultExporters($builder);
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
            editRoute: 'app_category_edit',
            deleteRoute: 'app_category_delete',
            deleteTranslationKey: 'entity.category.dialog.delete_confirm',
            idAccessor: static fn ($category) => $category->getId(),
            nameAccessor: static fn ($category) => $category->getName(),
        );
    }

    private function addRelationFilter(
        DataTableBuilderInterface $builder,
        string $name,
        string $label,
        string $association,
        string $alias,
        string $parameter,
        string $entityClass,
    ): void {
        $builder->addFilter($name, CallbackFilterType::class, [
            'label' => $label,
            'form_type' => EntityType::class,
            'form_options' => [
                'class' => $entityClass,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ],
            'active_filter_formatter' => fn ($data): string => implode(', ', array_map(
                static fn (object $entity): string => (string) $entity->getName(),
                $this->extractEntities($data, $entityClass),
            )),
            'callback' => function ($query, $data) use ($association, $alias, $parameter, $entityClass): void {
                $entities = $this->extractEntities($data, $entityClass);

                if ($entities === []) {
                    return;
                }

                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->leftJoin($association, $alias)
                    ->andWhere(sprintf('%s IN (:%s)', $alias, $parameter))
                    ->setParameter($parameter, $entities)
                    ->distinct();
            },
        ]);
    }

    /**
     * @return array<int, object>
     */
    private function extractEntities(mixed $data, string $expectedClass): array
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
            'data_class' => 'App\Entity\Category',
        ]);
    }
}
