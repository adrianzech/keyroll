<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\SSHKey;
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

class SSHKeyDataTableType extends AbstractDataTableType
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
                'label' => 'entity.ssh_key.label.name',
                'export' => [
                    'label' => $this->translator->trans('entity.ssh_key.label.name', [], 'messages'),
                ],
                'sort' => true,
            ])
            ->addColumn('user', TextColumnType::class, [
                'label' => 'entity.ssh_key.label.user',
                'export' => [
                    'label' => $this->translator->trans('entity.ssh_key.label.user', [], 'messages'),
                ],
                'sort' => true,
                'getter' => fn (SSHKey $sshKey) => $sshKey->getUser()?->getName() ?? '-',
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
                'label' => 'entity.ssh_key.label.name',
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->leftJoin('ssh_key.user', 'user')
                    ->andWhere('ssh_key.name LIKE :search OR user.name LIKE :search')
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
                    'href' => fn (SSHKey $sshKey) => $this->urlGenerator->generate('app_ssh_key_edit', [
                        'id' => $sshKey->getId(),
                    ]),
                    'label' => 'common.button.edit',
                    'variant' => 'light',
                ])
                ->addRowAction('delete', FormActionType::class, [
                    'action' => fn (SSHKey $sshKey) => $this->urlGenerator->generate('app_ssh_key_delete', [
                        'id' => $sshKey->getId(),
                    ]),
                    'method' => 'POST',
                    'label' => 'common.button.delete',
                    'variant' => 'danger',
                    'confirmation' => fn (SSHKey $sshKey) => [
                        'type' => 'danger',
                        'translation_domain' => 'messages',
                        'label_title' => 'common.dialog.delete_title',
                        'label_description' => $this->translator->trans('entity.ssh_key.dialog.delete_confirm', [
                            '%name%' => $sshKey->getName(),
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
            'data_class' => SSHKey::class,
        ]);
    }
}
