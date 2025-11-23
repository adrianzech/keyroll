<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Enum\HostConnectionStatus;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\DateFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\NumericFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Filter\Type\CallbackFilterType;
use Kreyu\Bundle\DataTableBundle\Pagination\PaginationData;
use Kreyu\Bundle\DataTableBundle\Type\AbstractDataTableType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HostDataTableType extends AbstractDataTableType
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
                'label' => 'entity.host.label.name',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.name', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('hostname', TextColumnType::class, [
                'label' => 'entity.host.label.hostname',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.hostname', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('port', TextColumnType::class, [
                'label' => 'entity.host.label.port',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.port', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('username', TextColumnType::class, [
                'label' => 'entity.host.label.username',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.username', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('categories', TextColumnType::class, [
                'label' => 'entity.host.label.categories',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.categories', [], 'messages'),
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
                'getter' => static fn ($host): array => $host->getCategories()->toArray(),
                'block_prefix' => 'category_badge',
            ])
            ->addColumn('connectionStatus', TextColumnType::class, [
                'label' => 'entity.host.label.connection_status',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.connection_status', [], 'messages'),
                    'formatter' => function (?HostConnectionStatus $status): string {
                        $translationKey = $status?->getLabelKey() ?? 'entity.host.status.unknown';

                        return $this->translator->trans($translationKey, [], 'messages');
                    },
                    'value_translation_domain' => false,
                ],
                'sort' => true,
                'block_prefix' => 'connection_status',
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
        $builder
            ->addFilter('name', StringFilterType::class, [
                'label' => 'entity.host.label.name',
            ])
            ->addFilter('hostname', StringFilterType::class, [
                'label' => 'entity.host.label.hostname',
            ])
            ->addFilter('username', StringFilterType::class, [
                'label' => 'entity.host.label.username',
            ])
            ->addFilter('port', NumericFilterType::class, [
                'label' => 'entity.host.label.port',
            ]);

        $this->configureCategoryFilter($builder);
        $this->configureStatusFilter($builder);

        $builder->addFilter('createdAt', DateFilterType::class, [
            'label' => 'common.label.created_at',
            'operator_selectable' => true,
        ]);
    }

    private function configureCategoryFilter(DataTableBuilderInterface $builder): void
    {
        $builder->addFilter('categories', CallbackFilterType::class, [
            'label' => 'entity.host.label.categories',
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
            'callback' => function ($query, $data): void {
                $categories = self::extractEntities($data, 'App\Entity\Category');

                if ($categories === []) {
                    return;
                }

                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->leftJoin('host.categories', 'filter_category')
                    ->andWhere('filter_category IN (:categories)')
                    ->setParameter('categories', $categories)
                    ->distinct();
            },
        ]);
    }

    private function configureStatusFilter(DataTableBuilderInterface $builder): void
    {
        $builder->addFilter('connectionStatus', CallbackFilterType::class, [
            'label' => 'entity.host.label.connection_status',
            'form_type' => ChoiceType::class,
            'form_options' => [
                'choices' => [
                    'entity.host.status.successful' => HostConnectionStatus::SUCCESSFUL,
                    'entity.host.status.failed' => HostConnectionStatus::FAILED,
                    'entity.host.status.checking' => HostConnectionStatus::CHECKING,
                    'entity.host.status.unknown' => HostConnectionStatus::UNKNOWN,
                ],
                'choice_translation_domain' => 'messages',
                'multiple' => true,
                'required' => false,
                'placeholder' => '',
            ],
            'active_filter_formatter' => fn ($data): string => implode(', ', array_map(
                fn (HostConnectionStatus $status): string => $this->translator->trans($status->getLabelKey(), [], 'messages'),
                self::extractEntities($data, HostConnectionStatus::class),
            )),
            'callback' => function ($query, $data): void {
                $statuses = self::extractEntities($data, HostConnectionStatus::class);

                if ($statuses === []) {
                    return;
                }

                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->andWhere('host.connectionStatus IN (:connectionStatuses)')
                    ->setParameter('connectionStatuses', $statuses);
            },
        ]);
    }

    private function configureSearch(DataTableBuilderInterface $builder): void
    {
        $builder->setSearchHandler(function ($query, string $search): void {
            /* @noinspection PhpUndefinedMethodInspection */
            $query
                ->leftJoin('host.categories', 'search_category')
                ->andWhere('host.name LIKE :search OR host.hostname LIKE :search OR host.username LIKE :search OR host.port LIKE :search OR host.connectionStatus LIKE :search OR search_category.name LIKE :search')
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
            editRoute: 'app_host_edit',
            deleteRoute: 'app_host_delete',
            deleteTranslationKey: 'entity.host.dialog.delete_confirm',
            idAccessor: static fn ($host) => $host->getId(),
            nameAccessor: static fn ($host) => $host->getName(),
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
            'data_class' => 'App\Entity\Host',
        ]);
    }
}
