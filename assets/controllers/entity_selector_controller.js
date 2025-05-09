import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        "searchInput",
        "resultsContainer",
        "selectedList",
        "noItemsMessage",
        "symfonyField"
    ];

    static values = {
        apiUrl: String,
        itemSingularName: String,
        initialData: Array,
    };

    connect() {
        this.selectedItemsMap = new Map();
        this.debounceTimer = null;
        this.DEBOUNCE_DELAY = 300; // milliseconds

        this.boundHideResultsOnClickOutside = this.hideResultsOnClickOutside.bind(this);
        document.addEventListener('click', this.boundHideResultsOnClickOutside);

        this.initialize();
    }

    disconnect() {
        document.removeEventListener('click', this.boundHideResultsOnClickOutside);
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }

    initialize() {
        if (this.initialDataValue && this.initialDataValue.length > 0) {
            this.initialDataValue.forEach(item => {
                if (item && typeof item.id !== 'undefined' && typeof item.name !== 'undefined') {
                    this.addItemToSelectedListDOM(item.id.toString(), item.name, false);
                }
            });
        }
        this.updateNoItemsMessageVisibility();
        this.updateSymfonySelectField();
    }

    hideResultsOnClickOutside(event) {
        const isClickInsideSearch = this.hasSearchInputTarget && this.searchInputTarget.contains(event.target);
        const isClickInsideResults = this.hasResultsContainerTarget && this.resultsContainerTarget.contains(event.target);

        if (!isClickInsideSearch && !isClickInsideResults && this.hasResultsContainerTarget) {
            this.resultsContainerTarget.classList.add('hidden');
        }
    }

    // --- Event Handlers (Actions) ---
    handleSearchInput(event) {
        const query = this.searchInputTarget.value.trim();
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.DEBOUNCE_DELAY);
    }

    handleFocus(event) {
        const query = this.searchInputTarget.value.trim();
        // Perform search immediately on focus to show initial list or current filtered list
        this.performSearch(query);
    }

    async performSearch(query) {
        const selectedIds = Array.from(this.selectedItemsMap.keys());
        const params = new URLSearchParams();
        params.append('query', query); // API handles empty query for initial list

        selectedIds.forEach(id => params.append('excluded_ids[]', id.toString()));

        try {
            const response = await fetch(`${this.apiUrlValue}?${params.toString()}`);
            if (!response.ok) {
                console.error(`Search request failed for ${this.itemSingularNameValue}: ${response.status} ${response.statusText}`);
                this.resultsContainerTarget.innerHTML = `<li><span class="px-4 py-2 text-error text-sm">Error loading results.</span></li>`;
                this.resultsContainerTarget.classList.remove('hidden'); // Show error
                return;
            }
            const items = await response.json();
            this.displaySearchResults(items);
        } catch (error) {
            console.error(`Error searching ${this.itemSingularNameValue}s:`, error);
            this.resultsContainerTarget.innerHTML = `<li><span class="px-4 py-2 text-error text-sm">Network error.</span></li>`;
            this.resultsContainerTarget.classList.remove('hidden'); // Show error
        }
    }

    displaySearchResults(items) {
        if (!this.hasResultsContainerTarget) return;
        this.resultsContainerTarget.innerHTML = '';
        if (items.length === 0) {
            this.resultsContainerTarget.innerHTML = `<li><span class="px-4 py-2 text-sm">No ${this.itemSingularNameValue}s found</span></li>`;
        } else {
            items.forEach(item => {
                const listItem = document.createElement('li');
                listItem.innerHTML = `<a href="#" class="text-sm p-2 block hover:bg-base-300 rounded-md"
                                         data-action="click->${this.identifier}#selectItem"
                                         data-item-id="${item.id}"
                                         data-item-name="${item.name}">${item.name}</a>`;
                this.resultsContainerTarget.appendChild(listItem);
            });
        }
        this.resultsContainerTarget.classList.remove('hidden');
    }

    selectItem(event) {
        event.preventDefault();
        const itemId = event.currentTarget.dataset.itemId;
        const itemName = event.currentTarget.dataset.itemName;

        this.addItemToSelectedListDOM(itemId, itemName);
        this.searchInputTarget.value = '';
        this.resultsContainerTarget.innerHTML = '';
        this.resultsContainerTarget.classList.add('hidden');
    }

    removeItem(event) {
        const itemDiv = event.currentTarget.closest('[data-item-id]');
        if (!itemDiv) return;

        const itemId = itemDiv.dataset.itemId;
        this.selectedItemsMap.delete(itemId);
        itemDiv.remove();
        this.updateNoItemsMessageVisibility();
        this.updateSymfonySelectField();
    }

    // --- DOM Manipulation & Logic (unchanged from previous version) ---
    updateNoItemsMessageVisibility() {
        if (this.hasNoItemsMessageTarget) {
            this.noItemsMessageTarget.classList.toggle('hidden', this.selectedItemsMap.size > 0);
        }
    }

    updateSymfonySelectField() {
        if (!this.hasSymfonyFieldTarget) return;

        Array.from(this.symfonyFieldTarget.options).forEach(option => {
            option.selected = false;
        });

        this.selectedItemsMap.forEach((name, id) => {
            let option = Array.from(this.symfonyFieldTarget.options).find(opt => opt.value === id.toString());
            if (option) {
                option.selected = true;
            } else {
                const newOption = new Option(name, id.toString(), true, true);
                this.symfonyFieldTarget.appendChild(newOption);
            }
        });
        this.symfonyFieldTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    addItemToSelectedListDOM(itemId, itemName, updateField = true) {
        if (this.selectedItemsMap.has(itemId)) {
            return;
        }
        this.selectedItemsMap.set(itemId, itemName);

        const itemDiv = document.createElement('div');
        itemDiv.dataset.itemId = itemId;
        itemDiv.className = 'flex justify-between items-center p-2 pl-3 bg-base-100/50 dark:bg-base-300/50';
        itemDiv.innerHTML = `<span class="truncate mr-2">${itemName}</span>
                             <button type="button"
                                     class="btn btn-square btn-sm btn-ghosttext-error hover:bg-error hover:text-error-content"
                                     aria-label="Remove ${itemName}"
                                     data-action="click->${this.identifier}#removeItem"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M7 21q-.825 0-1.412-.587T5 19V6H4V4h5V3h6v1h5v2h-1v13q0 .825-.587 1.413T17 21zm2-4h2V8H9zm4 0h2V8h-2z"/></svg></button>`;
        if (this.hasSelectedListTarget) {
            this.selectedListTarget.appendChild(itemDiv);
        }


        this.updateNoItemsMessageVisibility();
        if (updateField) {
            this.updateSymfonySelectField();
        }
    }
}
