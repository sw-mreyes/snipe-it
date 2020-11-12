<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::group([
            'middleware' => 'web',
            'namespace' => $this->namespace,
        ], function ($router) {
            require base_path('routes/web/hardware.php');
            require base_path('routes/web/models.php');
            require base_path('routes/web/accessories.php');
            require base_path('routes/web/licenses.php');
            require base_path('routes/web/consumables.php');
            require base_path('routes/web/fields.php');
            require base_path('routes/web/components.php');
            require base_path('routes/web/users.php');
            require base_path('routes/web/kits.php');
            require base_path('routes/web.php');
            // TODO(mre): confirm this is working as intended
            require base_path('routes/web/search.php');
            require base_path('routes/web/locations.php');
            require base_path('routes/web/reservations.php'); 
        });
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::group([
            'middleware' => 'auth:api',
            'namespace' => $this->namespace,
            'prefix' => 'api',
        ], function ($router) {
            require base_path('routes/api.php');
        });
    }
}
