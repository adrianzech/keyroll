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
use Symfony\Contracts\Translation\TranslatorInterface;

class CategoryDataTableType extends AbstractDataTableType
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
                'getter' => fn (Category $category) => $category->getHosts()->count(),
            ])
            ->addColumn('usersCount', TextColumnType::class, [
                'label' => 'entity.category.label.assigned_users_count',
                'export' => [
                    'label' => $this->translator->trans('entity.category.label.assigned_users_count', [], 'messages'),
                ],
                'sort' => false,
                'getter' => fn (Category $category) => $category->getUsers()->count(),
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
                'label' => 'entity.category.label.name',
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->andWhere('category.name LIKE :search')
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
                    'confirmation' => fn (Category $category) => [
                        'type' => 'danger',
                        'translation_domain' => 'messages',
                        'label_title' => 'common.dialog.delete_title',
                        'label_description' => $this->translator->trans('entity.category.dialog.delete_confirm', [
                            '%name%' => $category->getName(),
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
            'data_class' => Category::class,
        ]);
    }
}
