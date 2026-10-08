import template from './swag-search-logger-list.html.twig';

Shopware.Component.register('swag-search-logger-list', {
    template,

    data() {
        return {
            items: [],
            isLoading: false,
            searchTerm: '',
            sortBy: 'search_count',
            sortDirection: 'DESC',
            searchDebounce: null
        };
    },

    created() {
        this.loadItems();
    },

    beforeUnmount() {
        if (this.searchDebounce) {
            clearTimeout(this.searchDebounce);
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
            if (this.searchDebounce) {
                clearTimeout(this.searchDebounce);
            }
            this.searchDebounce = setTimeout(() => {
                this.loadItems();
            }, 300);
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
        }
    }
});
