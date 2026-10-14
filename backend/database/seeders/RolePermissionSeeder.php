<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.invite',
            'users.approve',
            'users.suspend',

            // Staff Directory
            'staff.view',
            'staff.create',
            'staff.update',
            'staff.delete',
            'staff.salary.view',
            'staff.salary.update',
            'staff.document.view',
            'staff.document.upload',
            'staff.document.delete',

            // Security
            'security.view',

            // Patients
            'patients.view',
            'patients.create',
            'patients.update',
            'patients.delete',

            // Appointments
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',
            'appointments.delete',

            // Clinical
            'vitals.view',
            'vitals.create',
            'vitals.update',
            'vitals.delete',

            'consultations.view',
            'consultations.create',
            'consultations.update',
            'consultations.delete',

            'diagnoses.view',
            'diagnoses.create',
            'diagnoses.update',
            'diagnoses.delete',

            'medical-records.view',
            'medical-records.create',
            'medical-records.update',
            'medical-records.delete',

            // Expert System
            'expert-system.view',
            'expert-system.use',

            // Prescriptions
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',
            'prescriptions.delete',

            // Medicines
            'medicines.view',
            'medicines.create',
            'medicines.update',
            'medicines.delete',

            // Inventory
            'inventory.view',
            'inventory.manage',

            // Billing
            'billing.view',
            'billing.create',
            'billing.update',
            'billing.delete',

            // Patient Documents
            'documents.view',
            'documents.manage',

            // Reports
            'reports.view',

            // Knowledge Base
            'knowledge-base.view',
            'knowledge-base.manage',

            // Audit
            'audit-logs.view',

            // Staff Communication
            'chat.view',
            'chat.create',
            'chat.send',
            'chat.manage',

            'notifications.view',
            'notifications.manage',

            'telegram.connect',
            'telegram.group.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'Admin',
            'Receptionist',
            'Nurse',
            'Doctor',
            'Pharmacist',
            'Patient',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        Role::findByName('Admin')->syncPermissions(
            Permission::all()
        );

        Role::findByName('Receptionist')->syncPermissions([
            'patients.view',
            'patients.create',
            'patients.update',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',
            'billing.view',
            'billing.create',
            'billing.update',
            'documents.view',
            'chat.view',
            'chat.create',
            'chat.send',
            'notifications.view',
            'notifications.manage',
            'telegram.connect',
        ]);

        Role::findByName('Nurse')->syncPermissions([
            'patients.view',
            'appointments.view',
            'vitals.view',
            'vitals.create',
            'vitals.update',
            'medical-records.view',
            'medical-records.create',
            'medical-records.update',
            'expert-system.view',
            'documents.view',
            'documents.manage',
            'chat.view',
            'chat.create',
            'chat.send',
            'notifications.view',
            'notifications.manage',
            'telegram.connect',
        ]);

        Role::findByName('Doctor')->syncPermissions([
            'patients.view',
            'appointments.view',
            'consultations.view',
            'consultations.create',
            'consultations.update',
            'diagnoses.view',
            'diagnoses.create',
            'diagnoses.update',
            'medical-records.view',
            'medical-records.create',
            'medical-records.update',
            'expert-system.view',
            'expert-system.use',
            'knowledge-base.view',
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',
            'medicines.view',
            'documents.view',
            'documents.manage',
            'chat.view',
            'chat.create',
            'chat.send',
            'notifications.view',
            'notifications.manage',
            'telegram.connect',
        ]);

        Role::findByName('Pharmacist')->syncPermissions([
            'patients.view',
            'prescriptions.view',
            'prescriptions.update',
            'medicines.view',
            'medicines.create',
            'medicines.update',
            'medicines.delete',
            'inventory.view',
            'inventory.manage',
            'chat.view',
            'chat.create',
            'chat.send',
            'notifications.view',
            'notifications.manage',
            'telegram.connect',
        ]);

        Role::findByName('Patient')->syncPermissions([
            'patients.view',
            'appointments.view',
            'appointments.create',
            'appointments.cancel',
            'medical-records.view',
            'prescriptions.view',
        ]);
    }
}
