<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Category;
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

class CategoryDataTableType extends AbstractDataTableType
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
                'label' => 'category.label.name',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('hostsCount', TextColumnType::class, [
                'label' => 'category.label.assigned_hosts_count',
                'export' => true,
                'sort' => false,
                'getter' => fn (Category $category) => $category->getHosts()->count(),
            ])
            ->addColumn('usersCount', TextColumnType::class, [
                'label' => 'category.label.assigned_users_count',
                'export' => true,
                'sort' => false,
                'getter' => fn (Category $category) => $category->getUsers()->count(),
            ])
            ->addColumn('createdAt', TextColumnType::class, [
                'label' => 'common.label.created_at',
                'export' => true,
                'sort' => true,
                'block_prefix' => 'time_ago',
            ])
            ->addFilter('name', StringFilterType::class, [
                'label' => 'category.label.name',
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                $query
                    ->andWhere('category.name LIKE :search')
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
                    'href' => fn (Category $category) => $this->urlGenerator->generate('app_category_edit', [
                        'id' => $category->getId(),
                    ]),
                    'label' => 'common.button.edit',
                    'variant' => 'light',
                ])
                ->addRowAction('delete', FormActionType::class, [
                    'action' => fn (Category $category) => $this->urlGenerator->generate('app_category_delete', [
                        'id' => $category->getId(),
                    ]),
                    'method' => 'POST',
                    'label' => 'common.button.delete',
                    'variant' => 'danger',
                    'confirmation' => [
                        'label_description' => 'category.alert.delete_confirm',
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
