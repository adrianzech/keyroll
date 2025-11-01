<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** @SuppressWarnings("PMD.TooManyFields") */
#[AsTwigComponent('button')]
final class Button
{
    // Basic button props
    public string $variant = 'primary';
    public string $size = 'md';
    public string $tag = 'button';
    public ?string $href = null;
    public string $type = 'button';
    public ?string $label = null;
    public ?string $icon = null;
    public string $iconPosition = 'left';
    public bool $disabled = false;
    public bool $loading = false;
    public bool $block = false;
    public bool $outline = false;
    public bool $ghost = false;
    public string $class = '';
    public ?string $form = null;
    public ?string $target = null;
    public ?string $onclick = null;

    // Confirmation modal props (optional)
    public ?string $confirmTitle = null;
    public ?string $confirmMessage = null;
    public ?string $confirmLabel = null;
    public ?string $cancelLabel = null;
    public ?string $closeLabel = null;
    public ?string $actionPath = null;
    public ?string $csrfToken = null;
    public string $csrfTokenName = '_token';

    // Cached modal ID (generated once)
    private ?string $cachedModalId = null;

    private const VARIANT_CLASSES = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'accent' => 'btn-accent',
        'success' => 'btn-success',
        'info' => 'btn-info',
        'warning' => 'btn-warning',
        'error' => 'btn-error',
        'ghost' => 'btn-ghost',
        'link' => 'btn-link',
        'neutral' => 'btn-neutral',
    ];

    private const SIZE_CLASSES = [
        'xs' => 'btn-xs',
        'sm' => 'btn-sm',
        'md' => '',
        'lg' => 'btn-lg',
    ];

    public function hasConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }

    public function getModalId(): string
    {
        // Generate once and cache it
        if ($this->cachedModalId === null) {
            $this->cachedModalId = 'button-modal-' . uniqid();
        }

        return $this->cachedModalId;
    }

    public function mount(): void
    {
        // Pre-generate modal ID if confirmation is needed
        if ($this->hasConfirmation()) {
            $this->getModalId();
        }
    }

    public function getComputedClasses(): string
    {
        $classes = ['btn'];

        if ($this->outline) {
            $classes[] = 'btn-outline';
        }

        if ($this->ghost) {
            $classes[] = 'btn-ghost';
        }

        $classes[] = self::VARIANT_CLASSES[$this->variant] ?? '';
        $classes[] = self::SIZE_CLASSES[$this->size] ?? '';

        if ($this->block) {
            $classes[] = 'btn-block';
        }

        if ($this->loading) {
            $classes[] = 'loading';
        }

        if ($this->class) {
            $classes[] = $this->class;
        }

        return implode(' ', array_filter($classes));
    }

    public function getComputedAttributes(): array
    {
        $attributes = [];

        // If has confirmation, override onclick
        if ($this->hasConfirmation()) {
            $attributes['onclick'] = "document.getElementById('{$this->getModalId()}').showModal()";

            return $attributes;
        }

        if ($this->tag === 'a' && $this->href) {
            $attributes['href'] = $this->href;
        }

        if ($this->tag === 'button') {
            $attributes['type'] = $this->type;
            if ($this->disabled) {
                $attributes['disabled'] = 'disabled';
            }
            if ($this->form) {
                $attributes['form'] = $this->form;
            }
        }

        if ($this->target) {
            $attributes['target'] = $this->target;
        }

        if ($this->onclick) {
            $attributes['onclick'] = $this->onclick;
        }

        return $attributes;
    }

    public function getComputedAttributesString(): string
    {
        $parts = [];
        foreach ($this->getComputedAttributes() as $attr => $value) {
            $parts[] = sprintf('%s="%s"', $attr, htmlspecialchars($value, ENT_QUOTES));
        }

        return implode(' ', $parts);
    }

    public function getComputedConfirmTitle(): string
    {
        return $this->confirmTitle ?? 'common.dialog.confirm_title';
    }

    public function getComputedConfirmLabel(): string
    {
        return $this->confirmLabel ?? $this->label ?? 'common.button.confirm';
    }

    public function getComputedCancelLabel(): string
    {
        return $this->cancelLabel ?? 'common.button.cancel';
    }

    public function getComputedCloseLabel(): string
    {
        return $this->closeLabel ?? 'common.action.close';
    }
}
