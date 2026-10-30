<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use App\Models\Company;
use App\Models\IntegrationSetting;
use App\Models\Niche;
use App\Models\PaymentSource;
use App\Models\Playbook;
use App\Models\Team;
use App\Models\User;
use App\Services\CallAnalysisService;
use Illuminate\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->firstOrCreate(
            ['slug' => 'comercial'],
            ['name' => 'Comercial', 'description' => 'Time comercial principal', 'active' => true]
        );

        foreach (User::query()->whereNull('team_id')->get() as $user) {
            $user->forceFill(['team_id' => $team->getKey()])->save();
        }

        $admin = User::query()->where('email', 'admin@closer.local')->first();

        if (! $admin) {
            $admin = User::query()->create([
                'name' => 'Admin Closer OS',
                'email' => 'admin@closer.local',
                'password' => env('DEMO_ADMIN_PASSWORD', 'Closer@2026!'),
                'role' => User::ROLE_ADMIN,
                'active' => true,
                'phone' => null,
                'avatar_color' => '#3fa373',
                'team_id' => $team->getKey(),
                'email_verified_at' => now(),
            ]);
        }

        if (! User::query()->where('role', User::ROLE_ADMIN)->where('active', true)->exists()) {
            $thalis = User::query()->where('email', 'thalis@closer.local')->first();
            $thalis?->forceFill(['role' => User::ROLE_ADMIN, 'team_id' => $team->getKey()])->save();
        }

        $owner = User::query()->where('email', '1thalisgabriel1@gmail.com')->first();

        if (! $owner) {
            $owner = User::query()->create([
                'name' => 'Thalis Gomes',
                'email' => '1thalisgabriel1@gmail.com',
                'password' => '123456',
                'role' => User::ROLE_ADMIN,
                'active' => true,
                'phone' => null,
                'avatar_color' => '#3fa373',
                'team_id' => $team->getKey(),
                'email_verified_at' => now(),
            ]);
        } else {
            $owner->forceFill([
                'name' => 'Thalis Gomes',
                'role' => User::ROLE_ADMIN,
                'active' => true,
                'team_id' => $owner->team_id ?? $team->getKey(),
            ])->save();
        }

        if (! User::query()->where('role', User::ROLE_MANAGER)->exists()) {
            User::query()->where('email', 'bruna@closer.local')->first()
                ?->forceFill(['role' => User::ROLE_MANAGER, 'team_id' => $team->getKey()])->save();
        }

        if (! User::query()->where('role', User::ROLE_SDR)->exists()) {
            User::query()->where('email', 'leticia@closer.local')->first()
                ?->forceFill(['role' => User::ROLE_SDR, 'team_id' => $team->getKey()])->save();
        }

        foreach (User::query()->whereNull('avatar_color')->get() as $index => $user) {
            $user->forceFill(['avatar_color' => ['#3fa373', '#4f8fd8', '#9b7fd8', '#d8a34f', '#c76b7f'][$index % 5]])->save();
        }

        $niches = [
            ['slug' => 'mercado_livre', 'name' => 'Mercado Livre', 'color' => '#7cb3e0', 'description' => 'Operação de e-commerce e marketplace.'],
            ['slug' => 'mente_renovada', 'name' => 'Mente Renovada', 'color' => '#9b7fd8', 'description' => 'Saúde, bem-estar e desenvolvimento pessoal.'],
            ['slug' => 'visagismo', 'name' => 'Visagismo', 'color' => '#d8a34f', 'description' => 'Beleza, imagem e consultoria de estilo.'],
            ['slug' => 'construcao_civil', 'name' => 'Construção Civil', 'color' => '#c76b7f', 'description' => 'Obras, reformas e serviços técnicos.'],
        ];

        foreach ($niches as $niche) {
            Niche::query()->firstOrCreate(['slug' => $niche['slug']], $niche + ['active' => true]);
        }

        foreach (PaymentSource::defaultSources() as $source) {
            PaymentSource::query()->firstOrCreate(['slug' => $source['slug']], $source + ['active' => true]);
        }

        $companies = [
            ['name' => 'Acme Corp', 'cnpj' => '12345678000100', 'email' => 'contato@acme.com', 'active' => true],
            ['name' => 'Global Ltda', 'cnpj' => '98765432000150', 'email' => 'vendas@global.com', 'active' => true],
            ['name' => 'Tech Solutions', 'cnpj' => '11223344000199', 'email' => 'suporte@techsolutions.com', 'active' => true],
        ];

        foreach ($companies as $companyData) {
            $company = Company::query()->firstOrCreate(['cnpj' => $companyData['cnpj']], $companyData);

            if ($owner && $company->wasRecentlyCreated) {
                $owner->companies()->attach($company->getKey());
            }
        }

        if ($owner && $owner->companies()->count() === 0) {
            Company::query()->where('active', true)->limit(1)->get()->each(fn ($c) => $owner->companies()->attach($c->getKey()));
        }

        if (! CommissionRule::query()->where('active', true)->exists()) {
            CommissionRule::query()->create([
                'name' => 'Comissão padrão',
                'scope' => 'global',
                'percentage' => 7,
                'fixed_amount' => null,
                'basis' => 'received',
                'active' => true,
                'priority' => 0,
                'created_by' => $admin->getKey(),
            ]);
        }

        if (! Playbook::query()->where('active', true)->exists()) {
            Playbook::query()->create([
                'niche_id' => null,
                'name' => 'Playbook consultivo padrão',
                'description' => 'Critérios heurísticos para calls de diagnóstico e fechamento.',
                'criteria' => CallAnalysisService::DEFAULT_CRITERIA,
                'version' => 1,
                'active' => true,
                'created_by' => $admin->getKey(),
            ]);
        }

        foreach (['google_calendar', 'whatsapp_cloud', 'ai', 'smtp'] as $provider) {
            IntegrationSetting::query()->firstOrCreate(
                ['provider' => $provider],
                ['enabled' => false, 'status' => 'not_configured', 'config' => []]
            );
        }
    }
}
