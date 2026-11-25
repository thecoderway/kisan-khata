<?php

namespace App\Modules\Users\Presentation\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Users\Application\CreateShopUser;
use App\Modules\Users\Application\ManageShopUserLifecycle;
use App\Modules\Users\Application\ShopUserCapacity;
use App\Modules\Users\Application\UserDirectoryQuery;
use App\Tenancy\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(
        UserDirectoryQuery $directory,
        ShopUserCapacity $capacity,
        CurrentShop $currentShop,
        Request $request,
    ): View {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();

        return view('users.index', [
            'users' => $directory->forActor($actor, $request),
            'capacity' => $actor->role === User::ROLE_OWNER
                ? $capacity->summary($currentShop->require())
                : null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', [User::class, User::ROLE_STAFF]);

        return $request->boolean('drawer')
            ? view('users.partials.create-form')
            : view('users.create');
    }

    public function lifecycleForm(Request $request, int $user, string $action): View
    {
        abort_unless(in_array($action, ['deactivate', 'reactivate', 'role'], true), 404);
        $member = User::query()->findOrFail($user);
        Gate::authorize('view', $member);

        if (($action === 'deactivate' && ! $member->is_active)
            || ($action === 'reactivate' && $member->is_active)) {
            abort(422, 'यह account इस कार्रवाई की स्थिति में नहीं है।');
        }

        return view('users.partials.lifecycle-form', compact('member', 'action'));
    }

    public function store(Request $request, CreateShopUser $createShopUser): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [User::class, User::ROLE_STAFF]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{8,20}$/', Rule::unique('users', 'mobile')],
            'role' => ['required', 'string', Rule::in([User::ROLE_MANAGER, User::ROLE_STAFF])],
            'temporary_password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $createShopUser->handle($request->user(), $data);

        $message = 'नया यूज़र सफलतापूर्वक बनाया गया। पहली बार लॉगिन पर नया पासवर्ड बनाना अनिवार्य होगा।';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message], 201);
        }

        return redirect()->route('users.index')->with('status', $message);
    }

    public function deactivate(
        Request $request,
        int $user,
        ManageShopUserLifecycle $lifecycle,
    ): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $lifecycle->deactivate($request->user(), $user, $data['reason'] ?? null);

        return $this->lifecycleResponse($request, 'यूज़र अकाउंट deactivated कर दिया गया।');
    }

    public function reactivate(
        Request $request,
        int $user,
        ManageShopUserLifecycle $lifecycle,
    ): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $lifecycle->reactivate($request->user(), $user, $data['reason'] ?? null);

        return $this->lifecycleResponse($request, 'यूज़र अकाउंट फिर से active कर दिया गया।');
    }

    public function changeRole(
        Request $request,
        int $user,
        ManageShopUserLifecycle $lifecycle,
    ): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in([User::ROLE_MANAGER, User::ROLE_STAFF])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $lifecycle->changeRole($request->user(), $user, $data['role'], $data['reason'] ?? null);

        return $this->lifecycleResponse($request, 'यूज़र की भूमिका सफलतापूर्वक बदल दी गई।');
    }

    private function lifecycleResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : back()->with('status', $message);
    }
}

