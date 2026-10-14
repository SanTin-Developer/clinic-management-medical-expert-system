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

            // Inpatient & nursing workflow
            'wards.view',
            'wards.manage',
            'beds.view',
            'beds.manage',
            'admissions.view',
            'admissions.create',
            'admissions.update',
            'admissions.discharge',
            'assignments.view',
            'assignments.manage',
            'nursing-tasks.view',
            'nursing-tasks.create',
            'nursing-tasks.update',
            'nursing-tasks.complete',
            'medications.view',
            'medications.record',

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
            'Head Nurse',
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
            'wards.view',
            'beds.view',
            'admissions.view',
            'admissions.create',
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
            'prescriptions.view',
            'medicines.view',
            'wards.view',
            'beds.view',
            'admissions.view',
            'assignments.view',
            'nursing-tasks.view',
            'nursing-tasks.create',
            'nursing-tasks.update',
            'nursing-tasks.complete',
            'medications.view',
            'medications.record',
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

        Role::findByName('Head Nurse')->syncPermissions([
            'users.view',
            'patients.view',
            'wards.view',
            'wards.manage',
            'beds.view',
            'beds.manage',
            'admissions.view',
            'admissions.update',
            'admissions.discharge',
            'assignments.view',
            'assignments.manage',
            'nursing-tasks.view',
            'nursing-tasks.create',
            'nursing-tasks.update',
            'nursing-tasks.complete',
            'medications.view',
            'medications.record',
            'vitals.view',
            'medical-records.view',
            'prescriptions.view',
            'medicines.view',
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
            'wards.view',
            'beds.view',
            'admissions.view',
            'admissions.create',
            'admissions.update',
            'nursing-tasks.view',
            'medications.view',
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
