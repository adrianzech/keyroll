<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\SSHKey;
use App\Entity\User;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\DateFilterType;
use Kreyu\Bundle\DataTableBundle\Bridge\Doctrine\Orm\Filter\Type\StringFilterType;
use Kreyu\Bundle\DataTableBundle\Column\Type\TextColumnType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Kreyu\Bundle\DataTableBundle\Filter\FilterData;
use Kreyu\Bundle\DataTableBundle\Filter\Type\CallbackFilterType;
use Kreyu\Bundle\DataTableBundle\Pagination\PaginationData;
use Kreyu\Bundle\DataTableBundle\Query\ProxyQueryInterface;
use Kreyu\Bundle\DataTableBundle\Type\AbstractDataTableType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SSHKeyDataTableType extends AbstractDataTableType
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TranslatorInterface $translator,
        private readonly DataTableConfigurator $dataTableConfigurator,
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
            ->addFilter('user', CallbackFilterType::class, [
                'label' => 'entity.ssh_key.label.user',
                'form_type' => EntityType::class,
                'form_options' => [
                    'class' => User::class,
                    'choice_label' => 'name',
                    'required' => false,
                ],
                'active_filter_formatter' => static fn (FilterData $data): string => $data->getValue() instanceof User
                    ? $data->getValue()->getName()
                    : '',
                'callback' => function (ProxyQueryInterface $query, FilterData $data): void {
                    if (!$data->hasValue()) {
                        return;
                    }

                    $user = $data->getValue();

                    if (!$user instanceof User) {
                        return;
                    }

                    /* @noinspection PhpUndefinedMethodInspection */
                    $query
                        ->andWhere('ssh_key.user = :user')
                        ->setParameter('user', $user);
                },
            ])
            ->addFilter('createdAt', DateFilterType::class, [
                'label' => 'common.label.created_at',
                'operator_selectable' => true,
            ])
            ->setSearchHandler(function (ProxyQueryInterface $query, string $search): void {
                /* @noinspection PhpUndefinedMethodInspection */
                $query
                    ->leftJoin('ssh_key.user', 'user')
                    ->andWhere('ssh_key.name LIKE :search OR user.name LIKE :search OR user.email LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            });

        $this->dataTableConfigurator->addDefaultExporters($builder);

        $builder->setDefaultPaginationData(
            new PaginationData(
                page: 1,
                perPage: 10,
            )
        );

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $this->dataTableConfigurator->addAdminActions(
                builder: $builder,
                editRoute: 'app_ssh_key_edit',
                deleteRoute: 'app_ssh_key_delete',
                deleteTranslationKey: 'entity.ssh_key.dialog.delete_confirm',
                idAccessor: static fn (SSHKey $sshKey) => $sshKey->getId(),
                nameAccessor: static fn (SSHKey $sshKey) => $sshKey->getName(),
            );
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
