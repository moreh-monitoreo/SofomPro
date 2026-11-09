<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Alta y baja del personal. Las cuentas de clientes se crean solas al aprobar su crédito y no se ven aquí.
 * Nadie puede quitarse a sí mismo el rol ni desactivarse, y siempre queda al menos un administrador activo.
 */
class UserController extends Controller
{
    public function index()
    {
        $users = User::role(User::STAFF_ROLES)
            ->with('roles')
            ->orderByRaw('deactivated_at IS NOT NULL')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->staffRole($user),
                'two_factor' => (bool) $user->two_factor_confirmed_at,
                'active' => $user->isActive(),
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'is_me' => $user->id === auth()->id(),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => User::ROLE_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
        ], [
            'email.unique' => 'Ese correo ya tiene una cuenta (puede ser de un cliente).',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Str::random(40),
            ]);
            $user->assignRole($data['role']);

            return $user;
        });

        AuditLogger::record('user.created', $user, after: ['email' => $user->email, 'role' => $data['role']]);
        $this->sendInvitation($user);

        return back()->with('message', "Se envió la invitación a {$user->email}.");
    }

    public function update(Request $request, User $user)
    {
        $this->ensureStaff($user);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
        ]);

        $before = ['name' => $user->name, 'role' => $this->staffRole($user)];

        if ($data['role'] !== $before['role']) {
            if ($user->id === auth()->id()) {
                return back()->withErrors(['role' => 'No puedes cambiar tu propio rol; pídeselo a otro administrador.']);
            }
            if ($before['role'] === 'Administrator' && $this->isLastAdministrator($user)) {
                return back()->withErrors(['role' => 'Debe quedar al menos un administrador activo.']);
            }
        }

        DB::transaction(function () use ($user, $data) {
            $user->update(['name' => $data['name']]);
            // Solo se cambian los roles del equipo; si la persona también es cliente, conserva ese rol
            $user->syncRoles($user->roles->pluck('name')->diff(User::STAFF_ROLES)->push($data['role'])->all());
        });

        AuditLogger::record('user.updated', $user, before: $before, after: ['name' => $user->name, 'role' => $data['role']]);

        return back()->with('message', 'Usuario actualizado.');
    }

    public function deactivate(User $user)
    {
        $this->ensureStaff($user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propia cuenta.']);
        }
        if ($this->isLastAdministrator($user)) {
            return back()->withErrors(['user' => 'Debe quedar al menos un administrador activo.']);
        }

        $user->forceFill(['deactivated_at' => now(), 'remember_token' => Str::random(60)])->save();
        // Las sesiones abiertas se cierran en su siguiente petición; con sesiones en base se borran de una vez
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        AuditLogger::record('user.deactivated', $user, after: ['email' => $user->email]);

        return back()->with('message', "{$user->name} ya no puede entrar al sistema.");
    }

    public function reactivate(User $user)
    {
        $this->ensureStaff($user);

        $user->forceFill(['deactivated_at' => null])->save();
        AuditLogger::record('user.reactivated', $user, after: ['email' => $user->email]);

        return back()->with('message', "{$user->name} puede volver a entrar.");
    }

    /** Para cuando alguien pierde el teléfono: deberá configurar la verificación en dos pasos al entrar. */
    public function resetTwoFactor(User $user, TwoFactorAuth $twoFactor)
    {
        $this->ensureStaff($user);

        $twoFactor->disable($user);
        AuditLogger::record('user.two_factor_reset', $user, after: ['email' => $user->email]);

        return back()->with('message', "{$user->name} deberá activar de nuevo la verificación en dos pasos.");
    }

    public function invite(User $user)
    {
        $this->ensureStaff($user);

        if (! $user->isActive()) {
            return back()->withErrors(['user' => 'Reactiva la cuenta antes de enviar la invitación.']);
        }

        $this->sendInvitation($user);

        return back()->with('message', "Se envió la invitación a {$user->email}.");
    }

    private function sendInvitation(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
        $hours = (int) round(config('auth.passwords.'.config('auth.defaults.passwords').'.expire') / 60);
        $role = User::ROLE_LABELS[$this->staffRole($user)]['label'] ?? $this->staffRole($user);

        Mail::to($user->email)->send(new StaffInvitation($user->name, $role, $url, $hours));
        AuditLogger::record('user.invited', $user, after: ['email' => $user->email]);
    }

    private function staffRole(User $user): ?string
    {
        return $user->roles->pluck('name')->first(fn ($name) => in_array($name, User::STAFF_ROLES, true));
    }

    private function isLastAdministrator(User $user): bool
    {
        if (! $user->hasRole('Administrator')) {
            return false;
        }

        return ! User::role('Administrator')->whereNull('deactivated_at')->whereKeyNot($user->id)->exists();
    }

    private function ensureStaff(User $user): void
    {
        abort_unless($user->isStaff(), 404);
    }
}
