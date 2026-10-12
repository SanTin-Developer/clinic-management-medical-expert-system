<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique, human-friendly username so users can sign in with either
     * their email address or username. Existing accounts are backfilled from
     * the local part of their email address.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('email');
        });

        $used = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email', 'name']) as $user) {
            $base = $this->slug((string) ($user->email ?: $user->name ?: 'user'));
            $username = $base;
            $suffix = 1;

            while (in_array($username, $used, true)) {
                $username = $base.$suffix;
                $suffix++;
            }

            $used[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    private function slug(string $value): string
    {
        $local = strstr($value, '@', true) ?: $value;
        $local = strtolower($local);
        $local = preg_replace('/[^a-z0-9._-]/', '', $local) ?? '';
        $local = trim($local, '._-');

        return $local !== '' ? $local : 'user';
    }
};
