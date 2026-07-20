<?php
namespace Database\Seeders;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\{RoleModel, Permission};
use Illuminate\Database\Seeder;
class RolePermissionSeeder extends Seeder {
    public function run(): void {
        $labels = [
            'system_admin'=>'مدير المنصة','super_admin'=>'مالك النظام','agency_admin'=>'مدير الوكالة',
            'agency_employee'=>'موظف وكالة','operations_manager'=>'مدير العمليات','campaign_manager'=>'مدير الحملات',
            'creator_manager'=>'مدير المؤثرين','finance'=>'المالية','content_reviewer'=>'مراجع المحتوى',
            'influencer'=>'مؤثر','ugc_creator'=>'صانع UGC','influencer_and_ugc'=>'مؤثر و UGC',
            'brand_admin'=>'مدير علامة','brand_member'=>'عضو علامة','external_agency_admin'=>'مدير وكالة خارجية',
            'external_agency_member'=>'عضو وكالة خارجية','viewer'=>'مشاهد',
        ];
        foreach (Role::cases() as $r) {
            RoleModel::updateOrCreate(['key'=>$r->value], ['label'=>$labels[$r->value] ?? $r->value]);
        }
        $perms = ['org.manage','members.manage','clients.manage','creators.manage','campaigns.manage',
            'collaborations.manage','content.review','finance.manage','integrations.manage','reports.view','settings.manage'];
        foreach ($perms as $p) { Permission::updateOrCreate(['key'=>$p], ['label'=>$p]); }
    }
}
