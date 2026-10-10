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

            // Clinical
            'vitals.view',
            'vitals.create',
            'vitals.update',

            'consultations.view',
            'consultations.create',
            'consultations.update',

            'diagnoses.view',
            'diagnoses.create',
            'diagnoses.update',

            'medical-records.view',
            'medical-records.create',
            'medical-records.update',

            // Expert System
            'expert-system.view',
            'expert-system.use',

            // Prescriptions
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',

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

            // Reports
            'reports.view',

            // Knowledge Base
            'knowledge-base.view',
            'knowledge-base.manage',

            // Audit
            'audit-logs.view',
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
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',
            'medicines.view',
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
