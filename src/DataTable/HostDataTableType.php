<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Category;
use App\Entity\Host;
use App\Enum\HostConnectionStatus;
use Kreyu\Bundle\DataTableBundle\Action\Type\ButtonActionType;
use Kreyu\Bundle\DataTableBundle\Action\Type\FormActionType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\CsvExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\OdsExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\XlsxExporterType;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Pagination\PaginationData;
use Kreyu\Bundle\DataTableBundle\Query\ProxyQueryInterface;
use Kreyu\Bundle\DataTableBundle\Type\AbstractDataTableType;
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
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->andWhere('host.name LIKE :search OR host.hostname LIKE :search OR host.username LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
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
