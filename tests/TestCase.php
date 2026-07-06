<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Tests;

use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Database\Schema\Blueprint;
use Dashed\DashedCore\DashedCoreServiceProvider;
use Dashed\DashedPages\DashedPagesServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Dashed\DashedLivechat\DashedLivechatServiceProvider;
use Dashed\DashedEcommerceCore\DashedEcommerceCoreServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Dashed\\DashedEcommerceCore\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function tearDown(): void
    {
        // De CMSManager-builderregistry is process-static. Testbench herboot de
        // service-providers per test en meerdere providers registreren bij elke
        // boot opnieuw, dus de registry groeit onbegrensd over een suite en
        // eet uiteindelijk het geheugen op. Terugzetten naar de defaults houdt
        // hem begrensd. (1:1 overgenomen van de root-app TestCase.)
        if (class_exists(\Dashed\DashedCore\CMSManager::class) && method_exists(\Dashed\DashedCore\CMSManager::class, 'resetBuilders')) {
            \Dashed\DashedCore\CMSManager::resetBuilders();
        }

        parent::tearDown();
    }

    protected function refreshApplication()
    {
        parent::refreshApplication();

        // Patch de sqlite-schema-grammar zodra de connectie geresolved wordt,
        // zodat de legacy dashed-migraties (`dropForeign('<naam>')`, dropColumn
        // op kolommen met unieke index/FK) op sqlite draaien. Zonder deze patch
        // gooit SQLite "does not support dropping foreign keys by name".
        // (1:1 overgenomen van de bewezen root-app TestCase.)
        $this->app->afterResolving('db.connection', function ($connection) {
            if ($connection->getDriverName() === 'sqlite') {
                $this->applySQLiteSchemaGrammarPatch($connection);
            }
        });

        $connection = $this->app->make('db')->connection();
        if ($connection->getDriverName() === 'sqlite') {
            $this->applySQLiteSchemaGrammarPatch($connection);
        }
    }

    private function applySQLiteSchemaGrammarPatch($connection): void
    {
        $connection->setSchemaGrammar(new class ($connection) extends SQLiteGrammar {
            public function compileDropForeign(Blueprint $blueprint, Fluent $command)
            {
                if (empty($command->columns)) {
                    return [];
                }

                return parent::compileDropForeign($blueprint, $command);
            }

            public function getAlterCommands(\Illuminate\Database\Connection $connection)
            {
                $commands = parent::getAlterCommands($connection);
                if (! in_array('dropColumn', $commands)) {
                    $commands[] = 'dropColumn';
                }

                return $commands;
            }

            public function compileDropColumn(Blueprint $blueprint, Fluent $command, \Illuminate\Database\Connection $connection)
            {
                return [];
            }

            public function compileAlter(Blueprint $blueprint, Fluent $command, \Illuminate\Database\Connection $connection)
            {
                $state = $blueprint->getState();
                $remainingColumnNames = array_map(
                    fn ($col) => $col->name,
                    iterator_to_array(collect($state->getColumns()))
                );

                $stateReflection = new \ReflectionObject($state);

                $fkProp = $stateReflection->getProperty('foreignKeys');
                $fkProp->setAccessible(true);
                $originalFks = $fkProp->getValue($state);
                $filteredFks = collect($originalFks)->filter(function ($fk) use ($remainingColumnNames) {
                    foreach ((array) $fk->columns as $fkColumn) {
                        if (! in_array($fkColumn, $remainingColumnNames)) {
                            return false;
                        }
                    }

                    return true;
                })->values()->all();
                $fkProp->setValue($state, $filteredFks);

                $idxProp = $stateReflection->getProperty('indexes');
                $idxProp->setAccessible(true);
                $originalIndexes = $idxProp->getValue($state);
                $filteredIndexes = collect($originalIndexes)->filter(function ($index) use ($remainingColumnNames) {
                    foreach ((array) $index->columns as $idxColumn) {
                        if (! in_array($idxColumn, $remainingColumnNames)) {
                            return false;
                        }
                    }

                    return true;
                })->values()->all();
                $idxProp->setValue($state, $filteredIndexes);

                try {
                    $result = parent::compileAlter($blueprint, $command, $connection);
                } finally {
                    $fkProp->setValue($state, $originalFks);
                    $idxProp->setValue($state, $originalIndexes);
                }

                return $result;
            }
        });
    }

    protected function defineDatabaseMigrations()
    {
        // Laravels basis-migraties (o.a. `users`) worden hier NIET via
        // `loadLaravelMigrations()` geladen: die spawnt een aparte migrator die
        // voor sqlite `:memory:` een nieuwe (lege) DB-handle opent, waardoor de
        // tabellen niet op de RefreshDatabase-connectie belanden. In plaats
        // daarvan maken we de basis-tabellen inline aan op dezelfde connectie.
        //
        // We maken `users` bovendien meteen met de dashed-core-kolommen
        // (`first_name` etc.), zodat de geguarde `extend_users_table`-migratie
        // (`if (! Schema::hasColumn('users', 'first_name'))`) haar
        // `->change()`-blok overslaat. Dat `->change()` rebuildt op
        // Laravel 11 + sqlite via een `__temp__users`-tabel met lege kolomlijst
        // ("create table __temp__users ()") -> syntaxfout.
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('role')->default('customer');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Enkele dashed-core-migraties (bv. `add_automatic_tries`) ALTEREN een
        // tabel die door een extern package (filament-media-library) wordt
        // aangemaakt en dat package zit niet in deze harness. Er is geen
        // hasTable-guard, dus we maken een minimale stub aan zodat de ALTERs
        // slagen. De smoke-test raakt deze tabel verder niet.
        if (! Schema::hasTable('filament_media_library')) {
            Schema::create('filament_media_library', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            DashedCoreServiceProvider::class,
            DashedPagesServiceProvider::class,
            DashedEcommerceCoreServiceProvider::class,
            DashedLivechatServiceProvider::class,
        ];

        if (class_exists(\Dashed\LaravelLocalization\LaravelLocalizationServiceProvider::class)) {
            array_unshift($providers, \Dashed\LaravelLocalization\LaravelLocalizationServiceProvider::class);
        }

        return $providers;
    }

    public function getEnvironmentSetUp($app)
    {
        // Enkele legacy dashed-migraties refereren aan `\App\Models\User` (de
        // app-User van een echte installatie, die `Dashed\DashedCore\Models\User`
        // extend). In het losse package-testbench bestaat die App-class niet, dus
        // aliassen we hem naar de core-User zodat die migraties booten.
        if (! class_exists(\App\Models\User::class, false)) {
            class_alias(\Dashed\DashedCore\Models\User::class, \App\Models\User::class);
        }

        // In het losse package-testbench bestaat er (anders dan in de root-app)
        // geen `testing`-connectie. `loadLaravelMigrations()` draait dan op de
        // sqlite-default terwijl de package-migraties op een (lege/andere)
        // connectie draaien -> `users` bestaat niet bij `extend_users_table` ->
        // `create table __temp__users ()`-syntaxfout. Door zowel `testing` als
        // de sqlite-default naar dezelfde in-memory sqlite te wijzen deelt alles
        // een DB en werkt de schema-introspectie bij `->change()`.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // dashed-core's frontend-auth-routes verwijzen naar
        // Dashed\DashedTranslations\Models\Translation. Dat package is een
        // optionele suggest (niet als dev-dep geinstalleerd in deze harness),
        // dus zetten we de auth-routes uit zodat het package-testbench-boot
        // niet op een ontbrekende Translation-class valt.
        $app['config']->set('dashed-core.default_auth_pages_enabled', false);

        // LaravelLocalizationServiceProvider::register() merges zijn eigen
        // (lege) config vóór dashed-core's `hasConfigFile('laravellocalization')`
        // dat doet; Laravel's mergeConfigFrom vult alleen ontbrekende keys, dus
        // de tweede merge wint niet. Zonder dit expliciet te zetten gooit
        // LaravelLocalization een UnsupportedLocaleException bij het booten.
        if (class_exists(\Dashed\LaravelLocalization\LaravelLocalizationServiceProvider::class)) {
            $app['config']->set('laravellocalization.supportedLocales', [
                'nl' => ['name' => 'Dutch', 'script' => 'Latn', 'native' => 'Nederlands', 'regional' => 'nl_NL'],
            ]);
            $app['config']->set('laravellocalization.defaultLocale', 'nl');
        }

        // Registreer een `main`-site in de CMS-builder. Sommige migraties/models
        // roepen `Sites::getFirstSite()` (== `builder('sites')[0]`); zonder een
        // geregistreerde site gooit dat "Undefined array key 0". De testdata
        // gebruikt `site_id => 'main'`, dus die id houden we aan.
        cms()->builder('sites', [
            [
                'id' => 'main',
                'name' => 'Main',
                'locales' => ['nl'],
            ],
        ]);
    }
}
