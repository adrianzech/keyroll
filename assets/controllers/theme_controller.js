import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["toggle"];

    connect() {
        // Get the initial theme from localStorage or default to 'light'
        const savedTheme = localStorage.getItem('theme') || 'light';

        // Update checkbox state based on the current theme
        if (this.hasToggleTarget) {
            this.toggleTarget.checked = savedTheme === 'dark';
        }
    }

    toggle(event) {
        const newTheme = event.currentTarget.checked ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        // Dispatch an event so other components can react to the theme change
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: {theme: newTheme}
        }));
    }
}