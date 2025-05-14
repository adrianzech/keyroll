import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

function initializeThemeToggle() {
    const themeToggle = document.getElementById('theme-toggle');

    // Get the initial theme from localStorage or default to 'light'
    const savedTheme = localStorage.getItem('theme') || 'light';

    // Update checkbox state based on the current theme
    if (themeToggle) {
        // Set the checkbox state to match current theme
        themeToggle.checked = savedTheme === 'dark';

        // Remove any existing event listeners to prevent duplicates
        const newToggle = themeToggle.cloneNode(true);
        themeToggle.parentNode.replaceChild(newToggle, themeToggle);

        // Add event listener to toggle theme
        newToggle.addEventListener('change', function () {
            const newTheme = this.checked ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            // Dispatch an event so other components can react to the theme change
            document.dispatchEvent(new CustomEvent('themeChanged', {
                detail: {theme: newTheme}
            }));
        });
    }
}


// Initialize on first page load
document.addEventListener('DOMContentLoaded', initializeThemeToggle);

// Initialize again after Turbo page loads (for Symfony UX Turbo)
document.addEventListener('turbo:load', initializeThemeToggle);

// For regular page navigations without Turbo
document.addEventListener('page:load', initializeThemeToggle);

// For legacy Turbo
document.addEventListener('turbolinks:load', initializeThemeToggle);
