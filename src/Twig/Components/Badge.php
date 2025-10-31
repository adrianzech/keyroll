<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('badge')]
final class Badge
{
    public string $type;
    public mixed $data;
    public string $size = 'sm';

    public function shouldTranslate(): bool
    {
        return match ($this->type) {
            'category' => false,
            default => true,
        };
    }

    public function getVariant(): string
    {
        return match ($this->type) {
            'user_role' => $this->getUserRoleVariant(),
            'connection_status' => $this->getConnectionStatusVariant(),
            'category' => 'primary',
            default => 'neutral',
        };
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            'user_role' => $this->getUserRoleLabel(),
            'connection_status' => $this->getConnectionStatusLabel(),
            'category' => $this->getCategoryLabel(),
            default => (string) $this->data,
        };
    }

    private function getUserRoleVariant(): string
    {
        return match ($this->data) {
            'ROLE_ADMIN' => 'secondary',
            'ROLE_USER' => 'primary',
            default => 'ghost',
        };
    }

    private function getUserRoleLabel(): string
    {
        return match ($this->data) {
            'ROLE_ADMIN' => 'entity.user.label.admin',
            'ROLE_USER' => 'entity.user.label.user',
            default => str_replace('ROLE_', '', $this->data),
        };
    }

    private function getConnectionStatusVariant(): string
    {
        if ($this->data === null) {
            return 'neutral';
        }

        // Assuming ConnectionStatus enum has getBadgeClass() method
        $badgeClass = $this->data->getBadgeClass();

        // Extract variant from badge class (e.g., 'badge-success' -> 'success')
        if (preg_match('/badge-(\w+)/', $badgeClass, $matches)) {
            return $matches[1];
        }

        return 'neutral';
    }

    private function getConnectionStatusLabel(): string
    {
        if ($this->data === null) {
            return 'entity.host.status.unknown';
        }

        // Assuming ConnectionStatus enum has getLabelKey() method
        return $this->data->getLabelKey();
    }

    private function getCategoryLabel(): string
    {
        if ($this->data === null) {
            return '';
        }

        // If data has a getName() method, use it (prioritize methods over property access)
        if (is_object($this->data) && method_exists($this->data, 'getName')) {
            return $this->data->getName();
        }

        return (string) $this->data;
    }

    public function getClasses(): string
    {
        $classes = ['badge', 'badge-soft'];

        $classes[] = match ($this->size) {
            'xs' => 'badge-xs',
            'sm' => 'badge-sm',
            'md' => '',
            'lg' => 'badge-lg',
            default => 'badge-sm',
        };

        $variant = $this->getVariant();
        if ($variant !== 'ghost') {
            $classes[] = 'badge-' . $variant;
        } else {
            $classes[] = 'badge-ghost';
        }

        return implode(' ', array_filter($classes));
    }
}
