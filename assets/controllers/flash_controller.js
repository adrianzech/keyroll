import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message'];
    static values = {
        timeout: Number,
        stagger: Number,
    };

    connect() {
        const baseTimeout = this.hasTimeoutValue ? this.timeoutValue : 5000;
        const stagger = this.hasStaggerValue ? this.staggerValue : 150;

        this.messageTargets.forEach((el, idx) => {
            const timer = window.setTimeout(() => this.dismissElement(el), baseTimeout + idx * stagger);
            el.dataset.flashTimer = String(timer);
        });
    }

    disconnect() {
        this.messageTargets.forEach((el) => {
            const timer = Number(el.dataset.flashTimer);
            if (timer) {
                clearTimeout(timer);
            }
        });
    }

    dismiss(event) {
        if (event) {
            event.preventDefault();
        }
        const el = event?.currentTarget?.closest('[data-flash-target="message"]') || null;
        if (el) {
            this.dismissElement(el);
        }
    }

    dismissElement(el) {
        if (!el || el.dataset.flashRemoving) {
            return;
        }

        el.dataset.flashRemoving = 'true';
        el.classList.add('opacity-0', 'translate-y-1');

        window.setTimeout(() => {
            el.remove();
        }, 200);
    }
}
