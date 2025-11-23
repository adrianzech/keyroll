import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static targets = ['list', 'item'];

    connect() {
        this.sortable = new Sortable(this.listTarget, {
            handle: '[data-drag-handle]',
            animation: 150,
            onEnd: () => this.updatePriorities(),
        });

        this.syncVisibility();
        this.updatePriorities();
    }

    disconnect() {
        if (this.sortable) {
            this.sortable.destroy();
        }
    }

    toggleVisibility(event) {
        const item = event.currentTarget.closest('[data-personalization-toggle-target="item"]');
        const visibilityInput = item?.querySelector('[data-personalization-toggle-visible-input]');

        if (visibilityInput) {
            visibilityInput.value = event.currentTarget.checked ? '1' : '0';
        }
    }

    syncVisibility() {
        this.itemTargets.forEach((item) => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            const visibilityInput = item.querySelector('[data-personalization-toggle-visible-input]');

            if (checkbox && visibilityInput) {
                visibilityInput.value = checkbox.checked ? '1' : '0';
            }
        });
    }

    updatePriorities() {
        const items = this.itemTargets;
        const length = items.length;

        items.forEach((item, index) => {
            const priorityInput = item.querySelector('[data-personalization-toggle-priority-input]');

            if (priorityInput) {
                priorityInput.value = length - index - 1;
            }
        });
    }
}
