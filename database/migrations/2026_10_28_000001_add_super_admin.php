<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin: one university-wide account above the campuses. It adds the
 * campuses and their OJT Coordinator accounts and manages the login page
 * photos. It belongs to no campus, so users.campus_id may now be empty.
 *
 * On a database already in use, a first Super Admin account is created if
 * there is none:
 *   ID PSU-ADMIN-001, password "password" - change it after signing in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->change();
        });

        // A fresh, empty database gets its roles and accounts from the seeder.
        if (! DB::table('campuses')->exists()) {
            return;
        }

        $now = now();
        if (! DB::table('roles')->where('code', 'super_admin')->exists()) {
            DB::table('roles')->insert(['code' => 'super_admin', 'name' => 'Super Admin', 'created_at' => $now, 'updated_at' => $now]);
        }
        $roleId = DB::table('roles')->where('code', 'super_admin')->value('id');

        if (! DB::table('users')->where('role_id', $roleId)->exists()
            && ! DB::table('users')->where('id_number', 'PSU-ADMIN-001')->exists()) {
            DB::table('users')->insert([
                'campus_id' => null,
                'role_id' => $roleId,
                'id_number' => 'PSU-ADMIN-001',
                'name' => 'Super Admin',
                'email' => DB::table('users')->where('email', 'superadmin@internlink.test')->exists() ? 'psu-admin-001@internlink.test' : 'superadmin@internlink.test',
                'password' => Hash::make('password'),
                'status' => 'active',
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'super_admin')->value('id');
        if ($roleId) {
            DB::table('users')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
        // users.campus_id stays nullable (no campus-less users remain).
    }
};
