<?php

namespace App\Services\Nursing;

use App\Models\Admission;
use App\Models\PatientAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Central authority for "can this user touch this patient?".
 *
 * Every other role keeps the existing permission-based access. Frontline nurses
 * are additionally restricted to the patients they are *currently* assigned to
 * (directly, or via a ward-level coverage assignment), so a nurse can never read
 * or write another nurse's patient even by guessing IDs.
 */
class PatientAccessService
{
    /**
     * Roles that are never subject to nurse-to-patient restriction. A user who
     * holds both the Nurse role and any of these keeps full (permission-based)
     * access.
     *
     * @var array<int, string>
     */
    public const UNRESTRICTED_ROLES = ['Admin', 'Doctor', 'Receptionist', 'Pharmacist', 'Head Nurse'];

    /**
     * The role whose members are locked down to their assigned patients.
     */
    public const RESTRICTED_ROLE = 'Nurse';

    public function isRestricted(User $user): bool
    {
        return $user->hasRole(self::RESTRICTED_ROLE)
            && ! $user->hasAnyRole(self::UNRESTRICTED_ROLES);
    }

    /**
     * Every patient id the user may currently access.
     *
     * @return Collection<int, int>
     */
    public function assignedPatientIds(User $user): Collection
    {
        if (! $this->isRestricted($user)) {
            return collect();
        }

        $assignments = PatientAssignment::query()
            ->active()
            ->where('nurse_id', $user->id);

        $directPatientIds = (clone $assignments)
            ->whereNotNull('patient_id')
            ->pluck('patient_id');

        $wardIds = (clone $assignments)
            ->whereNotNull('ward_id')
            ->pluck('ward_id');

        $wardPatientIds = $wardIds->isEmpty()
            ? collect()
            : Admission::query()
                ->where('status', Admission::STATUS_ADMITTED)
                ->whereIn('ward_id', $wardIds)
                ->pluck('patient_id');

        return $directPatientIds
            ->merge($wardPatientIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function canAccessPatient(User $user, ?int $patientId): bool
    {
        if ($patientId === null) {
            return false;
        }

        if (! $this->isRestricted($user)) {
            return true;
        }

        return $this->assignedPatientIds($user)->contains((int) $patientId);
    }

    /**
     * Constrain a query that selects from the patients table itself.
     */
    public function scopePatientQuery(Builder $query, User $user): Builder
    {
        if (! $this->isRestricted($user)) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getQualifiedKeyName(), $this->assignedPatientIds($user));
    }

    /**
     * Constrain a query that references a patient via a foreign key column.
     */
    public function scopeByPatientColumn(Builder $query, User $user, string $column = 'patient_id'): Builder
    {
        if (! $this->isRestricted($user)) {
            return $query;
        }

        return $query->whereIn($column, $this->assignedPatientIds($user));
    }

    /**
     * Constrain a query whose model belongs to a patient via a relation name.
     */
    public function scopeByPatientRelation(Builder $query, User $user, string $relation = 'patient'): Builder
    {
        if (! $this->isRestricted($user)) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $patient) use ($user) {
            $this->scopePatientQuery($patient, $user);
        });
    }
}
