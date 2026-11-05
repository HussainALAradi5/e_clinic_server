<?php

use App\Enums\Identity\RoleName;
use App\Models\Identity\User;
use App\Services\Bulk\BulkActionRegistry;
use App\Services\Transfer\TransferGuard;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([CountrySeeder::class, RolesAndPermissionsSeeder::class]);
});

/** Whether a fresh user with the role holds the right. */
function roleCan(RoleName $role, string $right): bool
{
    return User::factory()->withRole($role->value)->create()->can($right);
}

it('gives each role its own duties: doctors prescribe, the pharmacy dispenses, the desk books and takes payments', function (RoleName $role, array $can, array $cannot) {
    foreach ($can as $right) {
        expect(roleCan($role, $right))->toBeTrue("{$role->value} should have {$right}");
    }
    foreach ($cannot as $right) {
        expect(roleCan($role, $right))->toBeFalse("{$role->value} should not have {$right}");
    }
})->with([
    'doctor' => [RoleName::Doctor, ['prescriptions.create', 'orders.create', 'records.write', 'sick_leaves.create', 'written_documents.create', 'treatments.export', 'orders.export'], ['dispensing.dispense', 'dispensing.administer', 'payments.create', 'journal.post']],
    'nurse' => [RoleName::Nurse, ['dispensing.administer', 'appointments.check_in', 'written_documents.create', 'orders.export'], ['prescriptions.create', 'dispensing.dispense', 'sick_leaves.create', 'payments.create']],
    'pharmacist' => [RoleName::Pharmacist, ['dispensing.dispense', 'inventory.receive', 'prescriptions.read_all'], ['prescriptions.create', 'appointments.treat', 'payments.create']],
    'receptionist' => [RoleName::Receptionist, ['appointments.create', 'appointments.check_in', 'appointments.cancel', 'invoices.issue', 'payments.create', 'treatments.export'], ['appointments.treat', 'prescriptions.create', 'dispensing.dispense', 'journal.post']],
    'accountant' => [RoleName::Accountant, ['journal.post', 'journal.export', 'reports.export', 'refunds.approve', 'claims.decide'], ['prescriptions.create', 'appointments.treat', 'dispensing.dispense']],
    'support' => [RoleName::Support, ['tickets.read_all', 'tickets.resolve', 'tickets.export'], ['patients.read_all', 'prescriptions.read_all', 'invoices.read_all']],
    'admin' => [RoleName::Admin, ['users.create', 'roles.write', 'clinic_profile.write'], []],
]);

it('offers no export nobody may use: every exportable list has its right, held at least by the admin', function () {
    $admin = User::factory()->withRole(RoleName::Admin->value)->create();
    $guard = app(TransferGuard::class);

    foreach (app(BulkActionRegistry::class)->all() as $resource => $definition) {
        if ($definition->exportResource() !== null) {
            expect($guard->canExport($definition, $admin))->toBeTrue("nobody may export {$resource} ({$definition->exportPermission()})");
        }
    }
});
