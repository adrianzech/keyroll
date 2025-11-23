<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Category;
use App\Entity\Host;
use App\Enum\HostConnectionStatus;
use Kreyu\Bundle\DataTableBundle\Action\Type\ButtonActionType;
use Kreyu\Bundle\DataTableBundle\Action\Type\FormActionType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\DateFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\NumericFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\CsvExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\OdsExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\XlsxExporterType;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Filter\FilterData;
use Kreyu\Bundle\DataTableBundle\Filter\Operator;
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

class HostDataTableType extends AbstractDataTableType
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @SuppressWarnings("ExcessiveMethodLength")
     */
    public function buildDataTable(DataTableBuilderInterface $builder, array $options): void
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
                'getter' => static fn (Host $host, mixed ...$context): array => $host->getCategories()->toArray(),
                'block_prefix' => 'category_badge',
            ])
            ->addColumn('connectionStatus', TextColumnType::class, [
                'label' => 'entity.host.label.connection_status',
                'export' => [
                    'label' => $this->translator->trans('entity.host.label.connection_status', [], 'messages'),
                    'formatter' => function (?HostConnectionStatus $status, mixed ...$context): string {
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
            ])
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
                'supported_operators' => [
                    Operator::Equals,
                    Operator::GreaterThan,
                    Operator::LessThan,
                    Operator::GreaterThanEquals,
                    Operator::LessThanEquals,
                ],
                'operator_selectable' => true,
            ])
            ->addFilter('categories', CallbackFilterType::class, [
                'label' => 'entity.host.label.categories',
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
                    if (!$data->hasValue()) {
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

                    /* @noinspection PhpUndefinedMethodInspection */
                    $query
                        ->leftJoin('host.categories', 'filter_category')
                        ->andWhere('filter_category IN (:categories)')
                        ->setParameter('categories', $categories)
                        ->distinct();
                },
            ])
            ->addFilter('connectionStatus', CallbackFilterType::class, [
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
                'active_filter_formatter' => fn (FilterData $data): string => implode(', ', array_map(
                    fn (HostConnectionStatus $status): string => $this->translator->trans($status->getLabelKey(), [], 'messages'),
                    array_filter(
                        ($data->getValue() instanceof \Traversable ? iterator_to_array($data->getValue()) : (array) $data->getValue()),
                        static fn (mixed $status): bool => $status instanceof HostConnectionStatus,
                    ),
                )),
                'callback' => function (ProxyQueryInterface $query, FilterData $data): void {
                    if (!$data->hasValue()) {
                        return;
                    }

                    $rawStatuses = $data->getValue();
                    $statuses = array_filter(
                        $rawStatuses instanceof \Traversable ? iterator_to_array($rawStatuses) : (array) $rawStatuses,
                        static fn (mixed $status): bool => $status instanceof HostConnectionStatus,
                    );

                    if ($statuses === []) {
                        return;
                    }

                    /* @noinspection PhpUndefinedMethodInspection */
                    $query
                        ->andWhere('host.connectionStatus IN (:connectionStatuses)')
                        ->setParameter('connectionStatuses', $statuses);
                },
            ])
            ->addFilter('createdAt', DateFilterType::class, [
                'label' => 'common.label.created_at',
                'operator_selectable' => true,
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->leftJoin('host.categories', 'search_category')
                    ->andWhere('host.name LIKE :search OR host.hostname LIKE :search OR host.username LIKE :search OR host.port LIKE :search OR host.connectionStatus LIKE :search OR search_category.name LIKE :search')
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
                    'href' => fn (Host $host) => $this->urlGenerator->generate('app_host_edit', [
                        'id' => $host->getId(),
                    ]),
                    'label' => 'common.button.edit',
                    'variant' => 'light',
                ])
                ->addRowAction('delete', FormActionType::class, [
                    'action' => fn (Host $host) => $this->urlGenerator->generate('app_host_delete', [
                        'id' => $host->getId(),
                    ]),
                    'method' => 'POST',
                    'label' => 'common.button.delete',
                    'variant' => 'danger',
                    'confirmation' => fn (Host $host) => [
                        'type' => 'danger',
                        'translation_domain' => 'messages',
                        'label_title' => 'common.dialog.delete_title',
                        'label_description' => $this->translator->trans('entity.host.dialog.delete_confirm', [
                            '%name%' => $host->getName(),
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
            'data_class' => Host::class,
        ]);
    }
}
