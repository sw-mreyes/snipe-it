<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\User;
use Generator;

trait ProvidesDataForFullMultipleCompanySupportTesting
{
    public static function dataForFullMultipleCompanySupportTesting(): Generator
    {
        yield "User in a company should result in user's company_id being used" => [
            function () {
                $jedi = Company::factory()->create();
                $sith = Company::factory()->create();
                $luke = User::factory()->for($jedi)
                    ->createAccessories()
                    ->createAssets()
                    ->createComponents()
                    ->createConsumables()
                    ->createLicenses()
                    ->create();

                return [
                    'actor' => $luke,
                    'company_attempting_to_associate' => $sith,
                    'assertions' => function ($model) use ($jedi) {
                        // Sith is not in Luke's pivot (he belongs only to Jedi), so the
                        // submitted company is rejected and his single pivot company is used.
                        self::assertEquals($jedi->id, $model->company_id);
                    },
                ];
            },
        ];

        yield 'User without a company should result in company_id being null' => [
            function () {
                $userInNoCompany = User::factory()
                    ->createAccessories()
                    ->createAssets()
                    ->createComponents()
                    ->createConsumables()
                    ->createLicenses()
                    ->create(['company_id' => null]);

                return [
                    'actor' => $userInNoCompany,
                    'company_attempting_to_associate' => Company::factory()->create(),
                    'assertions' => function ($model) {
                        self::assertNull($model->company_id);
                    },
                ];
            },
        ];

        yield 'Super-User assigning across companies should result in company_id being set to what was provided' => [
            function () {
                $superUser = User::factory()->superuser()->create(['company_id' => null]);
                $company = Company::factory()->create();

                return [
                    'actor' => $superUser,
                    'company_attempting_to_associate' => $company,
                    'assertions' => function ($model) use ($company) {
                        self::assertEquals($model->company_id, $company->id);
                    },
                ];
            },
        ];
    }
}
