<?php

namespace AdminPayments\Providers;

use Admin\Providers\AdminHelperServiceProvider;

class ConfigServiceProvider extends AdminHelperServiceProvider
{
    private $packageConfigKey = 'admineshop';

    private function getPaymentsConfigPath()
    {
        return __DIR__.'/../Config/config.php';
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            $this->getPaymentsConfigPath(), $this->packageConfigKey
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //Merge crudadmin configs
        $this->mergeAdminConfigs(require __DIR__.'/../Config/admin.php');

        //Merge admineshop configs
        $this->mergeConfigs(
            require $this->getPaymentsConfigPath(),
            $this->packageConfigKey,
            [],
            [],
        );

        $this->pushComponentsPaths();

        $this->addPaymentLogChannel();

        $this->enablePaymentHooksCors();
    }

    private function addPaymentLogChannel()
    {
        $this->app['config']->set('logging.channels.payments', [
            'driver' => 'single',
            'path' => storage_path('logs/payments.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ]);
    }

    private function enablePaymentHooksCors()
    {
        $paths = config('cors.paths', []);

        $this->app['config']->set('cors.paths', array_values(array_unique(array_merge($paths, ['_store/*']))));
    }
}
