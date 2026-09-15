<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->foreignId('recipient_user_id')->nullable()->after('notification_recipient_id')->constrained('users')->nullOnDelete();
        });

        $usersByNumber = [];
        DB::table('users')
            ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->select(['users.id', 'users.name', 'users.phone_number', 'employees.whatsapp_number'])
            ->orderBy('users.id')
            ->get()
            ->each(function (object $user) use (&$usersByNumber): void {
                $normalized = $this->normalize((string) ($user->whatsapp_number ?: $user->phone_number));
                if ($normalized !== null && ! isset($usersByNumber[$normalized])) {
                    $usersByNumber[$normalized] = ['id' => (int) $user->id, 'name' => (string) $user->name];
                }
            });

        DB::table('notification_logs')->orderBy('id')->eachById(function (object $log) use ($usersByNumber): void {
            $user = null;
            if ($log->notification_recipient_id !== null) {
                $user = DB::table('notification_recipients')
                    ->join('users', 'users.id', '=', 'notification_recipients.user_id')
                    ->where('notification_recipients.id', $log->notification_recipient_id)
                    ->select(['users.id', 'users.name'])
                    ->first();
            }
            if ($user === null) {
                $normalized = $this->normalize((string) $log->destination);
                $user = $normalized === null ? null : ($usersByNumber[$normalized] ?? null);
            }
            if ($user !== null) {
                $userId = is_array($user) ? $user['id'] : $user->id;
                $name = is_array($user) ? $user['name'] : $user->name;
                DB::table('notification_logs')->where('id', $log->id)->update([
                    'recipient_user_id' => $userId,
                    'recipient_name' => $name,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipient_user_id');
        });
    }

    private function normalize(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number) ?: '';
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 16) {
            return null;
        }

        return $digits;
    }
};
