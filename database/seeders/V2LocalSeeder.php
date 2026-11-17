<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class V2LocalSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'ligue1_live_redesign'
            || config('database.connections.mysql.url')
            || config('mail.default') !== 'array') {
            throw new \LogicException('Comptes fictifs réservés à la base locale V2 et au mail simulé.');
        }

        foreach (['test-v2@example.test'=>'user', 'admin-v2@example.test'=>'admin'] as $email=>$role) {
            $user = User::firstOrCreate(['email'=>$email], ['password'=>'Test V2 local 2026!']);
            $user->forceFill(['role'=>$role, 'email_verified_at'=>$user->email_verified_at ?? now()])->save();
        }
    }
}
