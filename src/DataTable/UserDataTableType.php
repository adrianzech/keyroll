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
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                $query
                    ->andWhere('user.name LIKE :search OR user.email LIKE :search')
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
                        'translation_domain' => 'messages',
                        'label_title' => 'common.dialog.delete_title',
                        'label_description' => 'entity.user.dialog.delete_confirm',
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
