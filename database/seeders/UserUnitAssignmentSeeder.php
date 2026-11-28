<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserBusinessUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserUnitAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Cabut hak akses dari user lama untuk unit-unit tersebut
        $revokedEmails = [
            'yositaaliyanti12@gmail.com',
            'dwi.indri.liana@gmail.com',
            'asmarawildha@gmail.com',
            'laraspujiastuti156@gmail.com',
        ];

        $revokedUsers = User::whereIn('email', $revokedEmails)->get();
        foreach ($revokedUsers as $revokedUser) {
            UserBusinessUnit::where('user_id', $revokedUser->id)->delete();
        }

        $assignments = [
            'ferdinand.winoto@gmail.com' => [
                'name' => 'Ferdinand Winoto',
                'units' => ['abc', 'aqua', 'ccod', 'fmcg', 'xtrans'],
            ],
            'tasya.laisafi1@mekar-jaya.com' => [
                'name' => 'Tasya Laisafi',
                'units' => ['onbold'],
            ],
            'vionadelian@gmail.com' => [
                'name' => 'Viona Delian',
                'units' => ['sangria', 'mohala', 'iqos'],
            ],
            'fa.vionaa@gmail.com' => [
                'name' => 'Viona Delian (Portal)',
                'units' => ['sangria', 'mohala', 'iqos'],
            ],
            'fransiskalia21@gmail.com' => [
                'name' => 'Fransiska Lia',
                'units' => ['spbu', 'kandang'],
            ],
            'paskaliaspbu89@gmail.com' => [
                'name' => 'Fransiska Lia (Portal)',
                'units' => ['spbu', 'kandang'],
            ],
        ];

        foreach ($assignments as $email => $data) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                ]
            );

            // Bersihkan unit lama dan isi ulang unit baru
            UserBusinessUnit::where('user_id', $user->id)->delete();

            foreach ($data['units'] as $unitCode) {
                UserBusinessUnit::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'business_unit_code' => $unitCode,
                    ],
                    [
                        'can_edit' => true,
                    ]
                );
            }
        }
    }
}
