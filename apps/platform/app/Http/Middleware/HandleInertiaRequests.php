<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\StudentCommunicationGateService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('student.setting');
        $studentNotifications = null;

        if ($user?->role === UserRole::Student && $user->student) {
            $communicationGate = app(StudentCommunicationGateService::class)->payload($user->student);

            $studentNotifications = [
                'chat_url' => route('student.chat.show'),
                'unread_mentor_chat' => $communicationGate['unread_mentor_chat'],
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role?->label(),
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at?->toAtomString(),
                    'student' => $user->student ? [
                        'id' => $user->student->id,
                        'display_name' => $user->student->display_name,
                        'status' => $user->student->status,
                        'notes' => $user->student->notes,
                        'settings' => [
                            'can_manage_own_schedule' => $user->student->canManageOwnSchedule(),
                            'can_use_ad_hoc_timer' => $user->student->canUseAdHocTimer(),
                            'preferred_timezone' => $user->student->setting?->preferred_timezone,
                        ],
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'student_notifications' => $studentNotifications,
        ];
    }
}
