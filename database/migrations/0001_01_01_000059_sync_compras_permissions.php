<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            'compras.anular' => 'Anular compras',
            'compras.pdf.ver' => 'Ver PDF compras',
        ];

        foreach ($permissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['label' => $label, 'description' => $label, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $this->syncToRolesWith('compras.ver', 'compras.pdf.ver');
        $this->syncToRolesWith('compras.crear', 'compras.anular');
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', ['compras.anular', 'compras.pdf.ver'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    private function syncToRolesWith(string $sourcePermission, string $targetPermission): void
    {
        $sourceId = DB::table('permissions')->where('name', $sourcePermission)->value('id');
        $targetId = DB::table('permissions')->where('name', $targetPermission)->value('id');

        if (! $sourceId || ! $targetId) {
            return;
        }

        $roleIds = DB::table('permission_role')->where('permission_id', $sourceId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $targetId],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
