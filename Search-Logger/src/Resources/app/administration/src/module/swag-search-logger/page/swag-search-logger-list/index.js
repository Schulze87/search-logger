import template from './swag-search-logger-list.html.twig';

Shopware.Component.register('swag-search-logger-list', {
    template,

    data() {
        return {
            items: [],
            isLoading: false
        };
    },

    created() {
        this.loadItems();
    },

    methods: {
        async loadItems() {
            this.isLoading = true;
            const httpClient = Shopware.Application.getContainer('init').httpClient;

            try {
                const response = await httpClient.get(
                    '/_action/swag-search-log/list',
                    {
                        headers: {
                            Authorization: `Bearer ${Shopware.Context.api.authToken.access}`
                        }
                    }
                );

                this.items = response.data.data ?? [];
            } catch (error) {
                console.error('Fehler beim Laden der Suchanfragen:', error);
                this.items = [];
            } finally {
                this.isLoading = false;
            }
        }
    }
});
