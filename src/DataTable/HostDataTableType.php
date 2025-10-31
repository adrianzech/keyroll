<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Category;
use App\Entity\Host;
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

class HostDataTableType extends AbstractDataTableType
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function buildDataTable(DataTableBuilderInterface $builder, array $options): void
    {
        $builder
            ->addColumn('name', TextColumnType::class, [
                'label' => 'host.label.name',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('hostname', TextColumnType::class, [
                'label' => 'host.label.hostname',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('port', TextColumnType::class, [
                'label' => 'host.label.port',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('username', TextColumnType::class, [
                'label' => 'host.label.username',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('categories', TextColumnType::class, [
                'label' => 'host.label.categories',
                'export' => [
                    'formatter' => static function (array $categories): string {
                        return implode(', ', array_map(
                            static fn (Category $category): string => $category->getName(),
                            $categories,
                        ));
                    },
                ],
                'sort' => false,
                'getter' => static fn (Host $host): array => $host->getCategories()->toArray(),
                'block_prefix' => 'category_badge',
            ])
            ->addColumn('connectionStatus', TextColumnType::class, [
                'label' => 'host.label.connection_status',
                'export' => true,
                'sort' => true,
                'block_prefix' => 'connection_status',
            ])
            ->addColumn('createdAt', TextColumnType::class, [
                'label' => 'common.label.created_at',
                'export' => true,
                'sort' => true,
                'block_prefix' => 'time_ago',
            ])
            ->addFilter('name', StringFilterType::class, [
                'label' => 'host.label.name',
            ])
            ->addFilter('hostname', StringFilterType::class, [
                'label' => 'host.label.hostname',
            ])
            ->addFilter('username', StringFilterType::class, [
                'label' => 'host.label.username',
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->andWhere('host.name LIKE :search OR host.hostname LIKE :search OR host.username LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            })
            ->addExporter('csv', CsvExporterType::class, [
                'label' => 'tables.export.csv',
            ])
            ->addExporter('ods', OdsExporterType::class, [
                'label' => 'tables.export.ods',
            ])
            ->addExporter('xlsx', XlsxExporterType::class, [
                'label' => 'tables.export.xlsx',
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
                    'confirmation' => [
                        'label_description' => 'host.alert.delete_confirm',
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
