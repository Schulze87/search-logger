import template from './swag-search-logger-list.html.twig';

Shopware.Component.register('swag-search-logger-list', {
    template,

    data() {
        return {
            items: [],
            isLoading: false,
            searchTerm: '',
            dateFrom: null,
            dateTo: null,
            onlyZeroResults: false,
            sortBy: 'last_searched',
            sortDirection: 'DESC',
            filterDebounce: null,

            // NEU: Für den Löschen-Dialog
            showDeleteModal: false,
            termToDelete: null
        };
    },

    created() {
        this.loadItems();
    },

    beforeUnmount() {
        if (this.filterDebounce) {
            clearTimeout(this.filterDebounce);
        }
    },

    methods: {
        async loadItems() {
            this.isLoading = true;
            const httpClient = Shopware.Application.getContainer('init').httpClient;

            try {
                const response = await httpClient.get('/_action/swag-search-log/list', {
                    params: {
                        term: this.searchTerm,
                        dateFrom: this.normalizeDate(this.dateFrom),
                        dateTo: this.normalizeDate(this.dateTo),
                        onlyZeroResults: this.onlyZeroResults,
                        sortBy: this.sortBy,
                        sortDirection: this.sortDirection
                    }
                });

                this.items = response.data.data ?? [];
            } catch (error) {
                console.error('Fehler beim Laden der Suchanfragen:', error);
                this.items = [];
            } finally {
                this.isLoading = false;
            }
        },

        normalizeDate(value) {
            if (!value) return null;
            const text = String(value);
            const match = text.match(/^\d{4}-\d{2}-\d{2}/);
            return match ? match[0] : null;
        },

        onSearchTermChange() {
            if (this.filterDebounce) clearTimeout(this.filterDebounce);
            this.filterDebounce = setTimeout(() => this.loadItems(), 300);
        },

        onFilterChange() {
            this.loadItems();
        },

        onSort(column) {
            const property = column.dataIndex ?? column.property;
            if (this.sortBy === property) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = property;
                this.sortDirection = 'DESC';
            }
            this.loadItems();
        },

        resetFilters() {
            this.searchTerm = '';
            this.dateFrom = null;
            this.dateTo = null;
            this.onlyZeroResults = false;
            this.loadItems();
        },

        // NEU: Löschen-Button geklickt -> Dialog öffnen
        onDelete(term) {
            this.termToDelete = term;
            this.showDeleteModal = true;
        },

        // NEU: Dialog schließen ohne Löschen
        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.termToDelete = null;
        },

        // NEU: Löschen bestätigen
        async onConfirmDelete() {
            if (!this.termToDelete) return;

            const httpClient = Shopware.Application.getContainer('init').httpClient;

            try {
                await httpClient.delete('/_action/swag-search-log/delete', {
                    params: { term: this.termToDelete }
                });

                this.$createNotificationSuccess({
                    message: `Alle Einträge für "${this.termToDelete}" wurden gelöscht.`
                });

                this.showDeleteModal = false;
                this.termToDelete = null;
                this.loadItems();
            } catch (error) {
                console.error('Fehler beim Löschen:', error);
                this.$createNotificationError({
                    message: 'Die Einträge konnten nicht gelöscht werden.'
                });
            }
        }
    }
});
