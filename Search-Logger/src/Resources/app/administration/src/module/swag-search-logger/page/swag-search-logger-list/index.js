import template from './swag-search-logger-list.html.twig';

Shopware.Component.register('swag-search-logger-list', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            repository: null,
            items: null
        };
    },

    created() {
        this.repository = this.repositoryFactory.create('swag_search_log');
        this.loadItems();
    },

    methods: {
        async loadItems() {
            const criteria = new Shopware.Data.Criteria();
            criteria.setLimit(50);
            criteria.addSorting(Shopware.Data.Criteria.sort('createdAt', 'DESC'));

            this.items = await this.repository.search(criteria, Shopware.Context.api);
        }
    }
});
