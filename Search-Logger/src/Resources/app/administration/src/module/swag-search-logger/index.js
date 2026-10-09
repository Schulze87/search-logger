import './page/swag-search-logger-list';

Shopware.Module.register('swag-search-logger', {
    type: 'plugin',
    name: 'SearchLogger',
    title: 'swag-search-logger.general.mainMenuItemGeneral',
    description: 'swag-search-logger.general.description',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    routes: {
        list: {
            component: 'swag-search-logger-list',
            path: 'list'
        }
    },

    navigation: [{
        id: 'swag-search-logger-list',
        label: 'swag-search-logger.general.mainMenuItemGeneral',
        path: 'swag.search.logger.list',
        parent: 'sw-extension',
        position: 100
    }]
});
