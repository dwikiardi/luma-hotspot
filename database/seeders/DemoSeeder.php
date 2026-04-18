<?php

namespace Database\Seeders;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsEvent;
use App\Models\Device;
use App\Models\PortalConfig;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VisitorProfile;
use App\Models\WalledGarden;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = $this->createTenants();
        $this->createRouters($tenants);
        $this->createPortalConfigs($tenants);
        $this->createWalledGardens($tenants);
        $users = $this->createUsers();
        $this->createVisitorProfiles($tenants, $users);
        $this->createAnalyticsEvents($tenants, $users);
        $this->createAnalyticsDaily($tenants);
        $this->createTenantUsers($tenants);
    }

    private function createTenants(): array
    {
        return [
            'hotel' => Tenant::create([
                'name' => 'Hotel Merdeka',
                'slug' => 'hotel-merdeka',
                'venue_type' => 'hotel',
                'is_active' => true,
            ]),
            'cafe' => Tenant::create([
                'name' => 'Kafe Nusantara',
                'slug' => 'kafe-nusantara',
                'venue_type' => 'cafe',
                'is_active' => true,
            ]),
            'coworking' => Tenant::create([
                'name' => 'CoWork Surabaya',
                'slug' => 'cowork-surabaya',
                'venue_type' => 'coworking',
                'is_active' => true,
            ]),
        ];
    }

    private function createRouters(array $tenants): void
    {
        Router::create([
            'tenant_id' => $tenants['hotel']->id,
            'nas_identifier' => 'hotel-lantai1',
            'name' => 'Lantai 1',
            'location' => 'Gedung Utama Lt.1',
        ]);
        Router::create([
            'tenant_id' => $tenants['hotel']->id,
            'nas_identifier' => 'hotel-lantai2',
            'name' => 'Lantai 2',
            'location' => 'Gedung Utama Lt.2',
        ]);
        Router::create([
            'tenant_id' => $tenants['cafe']->id,
            'nas_identifier' => 'kafe-main',
            'name' => 'Main Area',
            'location' => 'Lobby Utama',
        ]);
        Router::create([
            'tenant_id' => $tenants['coworking']->id,
            'nas_identifier' => 'cowork-main',
            'name' => 'Main Floor',
            'location' => 'Lantai 1',
        ]);
    }

    private function createPortalConfigs(array $tenants): void
    {
        PortalConfig::create([
            'tenant_id' => $tenants['hotel']->id,
            'active_login_methods' => [
                'google' => true,
                'wa' => true,
                'email' => false,
                'room' => true,
                'promo' => false,
            ],
            'branding' => [
                'name' => 'Hotel Merdeka WiFi',
                'color' => '#6366f1',
                'logo' => null,
            ],
            'grace_period_seconds' => 28800,
            'grace_period_enabled' => true,
        ]);

        PortalConfig::create([
            'tenant_id' => $tenants['cafe']->id,
            'active_login_methods' => [
                'google' => true,
                'wa' => true,
                'email' => false,
                'room' => false,
                'promo' => false,
            ],
            'branding' => [
                'name' => 'Kafe Nusantara',
                'color' => '#f59e0b',
                'logo' => null,
            ],
            'grace_period_seconds' => 7200,
            'grace_period_enabled' => true,
        ]);

        PortalConfig::create([
            'tenant_id' => $tenants['coworking']->id,
            'active_login_methods' => [
                'google' => true,
                'wa' => false,
                'email' => true,
                'room' => false,
                'promo' => false,
            ],
            'branding' => [
                'name' => 'CoWork Surabaya',
                'color' => '#10b981',
                'logo' => null,
            ],
            'grace_period_seconds' => 14400,
            'grace_period_enabled' => true,
        ]);
    }

    private function createWalledGardens(array $tenants): void
    {
        $defaultDomains = [
            'accounts.google.com',
            '*.googleapis.com',
            'api.whatsapp.com',
            'wa.me',
            'captive.apple.com',
            '*.apple.com',
            'connectivitycheck.gstatic.com',
        ];

        foreach ($tenants as $tenant) {
            foreach ($defaultDomains as $domain) {
                WalledGarden::create([
                    'tenant_id' => $tenant->id,
                    'domain' => $domain,
                    'type' => 'domain',
                    'is_active' => true,
                    'description' => 'Preset domain',
                ]);
            }
        }
    }

    private function createUsers(): Collection
    {
        $users = [];
        $identityTypes = ['google', 'wa', 'email', 'room'];
        $loginMethods = ['google', 'wa', 'email', 'room'];

        for ($i = 1; $i <= 100; $i++) {
            $type = $identityTypes[array_rand($identityTypes)];
            $method = $loginMethods[array_rand($loginMethods)];

            $user = User::create([
                'identity_value' => "user{$i}@example.com",
                'identity_type' => $type,
                'name' => "User Demo {$i}",
                'avatar' => null,
            ]);

            Device::create([
                'user_id' => $user->id,
                'fingerprint_hash' => hash('sha256', "fingerprint{$i}"),
            ]);

            $users[] = $user;
        }

        return collect($users);
    }

    private function createVisitorProfiles(array $tenants, $users): void
    {
        foreach ($tenants as $tenant) {
            foreach ($users as $index => $user) {
                $totalVisits = fake()->numberBetween(1, 20);
                $visitorType = match (true) {
                    $totalVisits >= 10 => 'loyal',
                    $totalVisits >= 5 => 'regular',
                    $totalVisits >= 2 => 'returning',
                    default => 'new',
                };

                VisitorProfile::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'total_visits' => $totalVisits,
                    'total_sessions' => $totalVisits + fake()->numberBetween(0, 5),
                    'first_visit_at' => now()->subDays(fake()->numberBetween(1, 60)),
                    'last_visit_at' => now()->subDays(fake()->numberBetween(0, 7)),
                    'avg_session_minutes' => fake()->numberBetween(15, 180),
                    'preferred_login_method' => ['google', 'wa', 'email', 'room'][array_rand(['google', 'wa', 'email', 'room'])],
                    'visitor_type' => $visitorType,
                ]);
            }
        }
    }

    private function createAnalyticsEvents(array $tenants, $users): void
    {
        $routers = Router::all();
        $eventTypes = [
            'portal_opened',
            'login_success',
            'login_failed',
            'auto_reconnect',
            'forced_relogin',
            'session_start',
            'session_end',
        ];

        for ($day = 30; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);

            foreach ($routers as $router) {
                $eventsPerDay = fake()->numberBetween(10, 50);

                for ($e = 0; $e < $eventsPerDay; $e++) {
                    $eventType = $eventTypes[array_rand($eventTypes)];
                    $user = $users->random();
                    $method = ['google', 'wa', 'email', 'room'][array_rand(['google', 'wa', 'email', 'room'])];

                    AnalyticsEvent::create([
                        'tenant_id' => $router->tenant_id,
                        'router_id' => $router->id,
                        'user_id' => $user->id,
                        'device_id' => $user->devices->first()?->id,
                        'event_type' => $eventType,
                        'mac_address' => strtoupper(fake()->macAddress()),
                        'ip_address' => fake()->ipv4(),
                        'login_method' => $eventType === 'login_success' ? $method : null,
                        'meta' => [
                            'source' => 'demo_seeder',
                        ],
                        'occurred_at' => $date->copy()->addHours(fake()->numberBetween(6, 23))
                            ->addMinutes(fake()->numberBetween(0, 59)),
                    ]);
                }
            }
        }
    }

    private function createAnalyticsDaily(array $tenants): void
    {
        $routers = Router::all();

        for ($day = 30; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);

            foreach ($routers as $router) {
                $uniqueVisitors = fake()->numberBetween(20, 100);
                $totalSessions = fake()->numberBetween(30, 150);
                $autoReconnects = fake()->numberBetween(5, 30);
                $forcedRelogins = fake()->numberBetween(1, 10);
                $totalAttempts = $autoReconnects + $forcedRelogins;

                AnalyticsDaily::create([
                    'tenant_id' => $router->tenant_id,
                    'router_id' => $router->id,
                    'date' => $date->toDateString(),
                    'unique_visitors' => $uniqueVisitors,
                    'total_sessions' => $totalSessions,
                    'new_visitors' => fake()->numberBetween(5, 30),
                    'returning_visitors' => fake()->numberBetween(10, 50),
                    'auto_reconnects' => $autoReconnects,
                    'forced_relogins' => $forcedRelogins,
                    'reconnect_rate' => $totalAttempts > 0
                        ? round(($autoReconnects / $totalAttempts) * 100, 2)
                        : 0,
                    'login_google' => fake()->numberBetween(10, 40),
                    'login_wa' => fake()->numberBetween(5, 25),
                    'login_room' => fake()->numberBetween(0, 15),
                    'login_email' => fake()->numberBetween(0, 10),
                    'avg_session_minutes' => fake()->numberBetween(20, 120),
                    'peak_hour' => fake()->randomElement([10, 11, 12, 19, 20, 21]),
                ]);
            }
        }
    }

    private function createTenantUsers(array $tenants): void
    {
        TenantUser::updateOrCreate(
            ['email' => 'owner@hotelmerdeka.com'],
            [
                'tenant_id' => $tenants['hotel']->id,
                'name' => 'Owner Hotel Merdeka',
                'email' => 'owner@hotelmerdeka.com',
                'password' => bcrypt('password'),
                'role' => 'owner',
            ]
        );

        TenantUser::updateOrCreate(
            ['email' => 'owner@kafenusantara.com'],
            [
                'tenant_id' => $tenants['cafe']->id,
                'name' => 'Owner Kafe Nusantara',
                'email' => 'owner@kafenusantara.com',
                'password' => bcrypt('password'),
                'role' => 'owner',
            ]
        );

        TenantUser::updateOrCreate(
            ['email' => 'owner@coworksurabaya.com'],
            [
                'tenant_id' => $tenants['coworking']->id,
                'name' => 'Owner CoWork Surabaya',
                'email' => 'owner@coworksurabaya.com',
                'password' => bcrypt('password'),
                'role' => 'owner',
            ]
        );
    }
}
