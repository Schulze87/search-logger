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
            salesChannelId: null,
            languageId: null,
            salesChannelOptions: [],
            languageOptions: [],
            sortBy: 'last_searched',
            sortDirection: 'DESC',
            filterDebounce: null,
            showDeleteModal: false,
            termToDelete: null,
            selectedItems: {},
            showBulkDeleteModal: false
        };
    },

    computed: {
        salesChannelSelectOptions() {
            return this.salesChannelOptions.map((option) => ({
                value: option.id,
                label: option.name
            }));
        },

        languageSelectOptions() {
            return this.languageOptions.map((option) => ({
                value: option.id,
                label: option.name
            }));
        },

        selectedCount() {
            return Object.keys(this.selectedItems).length;
        },

        selectedTerms() {
            return Object.values(this.selectedItems).map((item) => item.term);
        }
    },

    watch: {
        onlyZeroResults() {
            this.loadItems();
        }
    },

    created() {
        this.loadFilterOptions();
        this.loadItems();
    },

    beforeUnmount() {
        if (this.filterDebounce) {
            clearTimeout(this.filterDebounce);
        }
    },

    methods: {
        async loadFilterOptions() {
            const httpClient = Shopware.Application.getContainer('init').httpClient;

            try {
                const response = await httpClient.get('/_action/swag-search-log/filter-options');
                this.salesChannelOptions = response.data.salesChannels ?? [];
                this.languageOptions = response.data.languages ?? [];
            } catch (error) {
                console.error('Fehler beim Laden der Filteroptionen:', error);
            }
        },

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
                        salesChannelId: this.salesChannelId,
                        languageId: this.languageId,
                        sortBy: this.sortBy,
                        sortDirection: this.sortDirection
                    }
                });

                this.items = response.data.data ?? [];
                this.selectedItems = {};
            } catch (error) {
                console.error('Fehler beim Laden der Suchanfragen:', error);
                this.items = [];
                this.selectedItems = {};
            } finally {
                this.isLoading = false;
            }
        },

        normalizeDate(value) {
            if (!value) {
                return null;
            }

            const text = String(value);
            const match = text.match(/^\d{4}-\d{2}-\d{2}/);

            return match ? match[0] : null;
        },

        onSearchTermChange() {
            if (this.filterDebounce) {
                clearTimeout(this.filterDebounce);
            }
            this.filterDebounce = setTimeout(() => {
                this.loadItems();
            }, 300);
        },

        onFilterChange() {
            this.loadItems();
        },

        onSalesChannelChange(value) {
            this.salesChannelId = value ?? null;
            this.loadItems();
        },

        onLanguageChange(value) {
            this.languageId = value ?? null;
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
            this.salesChannelId = null;
            this.languageId = null;
            this.loadItems();
        },

        onSelectionChange(selection) {
            this.selectedItems = selection;
        },

        onBulkDelete() {
            if (this.selectedCount === 0) {
                return;
            }

            this.showBulkDeleteModal = true;
        },

        onCloseBulkDeleteModal() {
            this.showBulkDeleteModal = false;
        },

        async onConfirmBulkDelete() {
            if (this.selectedCount === 0) {
                return;
            }

            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const terms = this.selectedTerms;

            this.showBulkDeleteModal = false;

            try {
                await httpClient.delete('/_action/swag-search-log/delete', {
                    params: { terms },
                    paramsSerializer: (params) => {
                        const searchParams = new URLSearchParams();
                        (params.terms ?? []).forEach((term) => {
                            searchParams.append('terms[]', term);
                        });
                        return searchParams.toString();
                    }
                });

                await this.loadItems();

                this.createNotificationSuccess({
                    message: `${terms.length} Begriff(e) wurden gelöscht.`
                });
            } catch (error) {
                console.error('Fehler beim Löschen:', error);
                this.createNotificationError({
                    message: 'Die Einträge konnten nicht gelöscht werden.'
                });
            }
        },

        onDelete(term) {
            this.termToDelete = term;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.termToDelete = null;
        },

        async onConfirmDelete() {
            if (!this.termToDelete) {
                return;
            }

            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const deletedTerm = this.termToDelete;

            this.showDeleteModal = false;
            this.termToDelete = null;

            try {
                await httpClient.delete('/_action/swag-search-log/delete', {
                    params: { term: deletedTerm }
                });

                await this.loadItems();

                this.createNotificationSuccess({
                    message: `Alle Einträge für "${deletedTerm}" wurden gelöscht.`
                });
            } catch (error) {
                console.error('Fehler beim Löschen:', error);
                this.createNotificationError({
                    message: 'Die Einträge konnten nicht gelöscht werden.'
                });
            }
        }
    }
});
