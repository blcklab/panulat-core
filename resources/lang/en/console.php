<?php

declare(strict_types=1);

return [
    'list_title' => 'Available Panulat commands:',
    'alias_label' => 'aliases',
    'command_not_found' => 'Command [:name] not found.',
    'created' => 'Created :path',
    'removed' => 'Removed :path',
    'config_cached' => 'Configuration cached.',
    'routes_cached' => 'Routes cached.',
    'container_cached' => 'Container metadata cached.',
    'optimized' => 'Panulat optimized.',
    'optimization_cleared' => 'Optimization cache cleared.',
    'no_database' => 'No database connection is configured.',
    'migrated' => 'Migrated :name',
    'nothing_to_migrate' => 'Nothing to migrate.',
    'seeded' => 'Seeded :class',
    'description.config_cache' => 'Compile configuration into bootstrap/cache/config.php.',
    'description.route_cache' => 'Compile routes into bootstrap/cache/routes.php.',
    'description.container_cache' => 'Warm and cache container reflection metadata.',
    'description.optimize' => 'Cache config, routes, and container metadata for production.',
    'description.optimize_clear' => 'Remove compiled config, route, and container cache files.',
    'description.performance_check' => 'Check whether production performance caches and PHP settings are ready.',
    'description.make_controller' => 'Create an application controller in app/Controllers.',
    'description.make_middleware' => 'Create application middleware in app/Middleware.',
    'description.make_model' => 'Create an application model in app/Models.',
    'description.make_resource' => 'Create an application resource in app/Resources.',
    'description.make_migration' => 'Create a native PHP migration file in database/migrations.',
    'description.make_seeder' => 'Create a database seeder in database/seeders.',
    'description.migrate' => 'Run pending native PHP migration files.',
    'description.db_seed' => 'Run database seeders from database/seeders.',
];
