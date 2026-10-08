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
            sortBy: 'last_searched',
            sortDirection: 'DESC',
            filterDebounce: null
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
                        dateFrom: this.dateFrom,
                        dateTo: this.dateTo,
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
            this.loadItems();
        }
    }
});
