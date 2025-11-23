<?php

declare(strict_types=1);

namespace App\DataTable;

use Kreyu\Bundle\DataTableBundle\Action\Type\ButtonActionType;
use Kreyu\Bundle\DataTableBundle\Action\Type\FormActionType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\CsvExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\OdsExporterType;
use Kreyu\Bundle\DataTableBundle\Bridge\OpenSpout\Exporter\Type\XlsxExporterType;
use Kreyu\Bundle\DataTableBundle\DataTableBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataTableConfigurator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function addDefaultExporters(DataTableBuilderInterface $builder): void
    {
        $builder
            ->addExporter('csv', CsvExporterType::class, [
                'label' => 'data_table.export.csv',
            ])
            ->addExporter('ods', OdsExporterType::class, [
                'label' => 'data_table.export.ods',
            ])
            ->addExporter('xlsx', XlsxExporterType::class, [
                'label' => 'data_table.export.xlsx',
            ]);
    }

    public function addAdminActions(
        DataTableBuilderInterface $builder,
        string $editRoute,
        string $deleteRoute,
        string $deleteTranslationKey,
        callable $idAccessor,
        callable $nameAccessor,
        string $translationDomain = 'messages',
    ): void {
        $builder
            ->addRowAction('edit', ButtonActionType::class, [
                'href' => fn ($entity) => $this->urlGenerator->generate($editRoute, [
                    'id' => $idAccessor($entity),
                ]),
                'label' => 'common.button.edit',
                'variant' => 'light',
            ])
            ->addRowAction('delete', FormActionType::class, [
                'action' => fn ($entity) => $this->urlGenerator->generate($deleteRoute, [
                    'id' => $idAccessor($entity),
                ]),
                'method' => 'POST',
                'label' => 'common.button.delete',
                'variant' => 'danger',
                'confirmation' => fn ($entity) => [
                    'type' => 'danger',
                    'translation_domain' => $translationDomain,
                    'label_title' => 'common.dialog.delete_title',
                    'label_description' => $this->translator->trans($deleteTranslationKey, [
                        '%name%' => $nameAccessor($entity),
                    ], $translationDomain),
                    'label_confirm' => 'common.button.delete',
                    'label_cancel' => 'common.button.cancel',
                ],
            ]);
    }
}
