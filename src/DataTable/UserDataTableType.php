<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\User;
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

class UserDataTableType extends AbstractDataTableType
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
                'label' => 'user.label.name',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('email', TextColumnType::class, [
                'label' => 'user.label.email',
                'export' => true,
                'sort' => true,
            ])
            ->addColumn('primaryRole', TextColumnType::class, [
                'label' => 'user.label.role',
                'export' => true,
                'sort' => false,
                'block_prefix' => 'role_badge',
            ])
            ->addFilter('name', StringFilterType::class, [
                'label' => 'user.label.name',
            ])
            ->addFilter('email', StringFilterType::class, [
                'label' => 'user.label.email',
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                $query
                    ->andWhere('user.name LIKE :search OR user.email LIKE :search')
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
                    'confirmation' => [
                        'label_description' => 'user.confirm_delete',
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
