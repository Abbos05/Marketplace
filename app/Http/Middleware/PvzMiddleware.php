<?php



namespace App\Http\Middleware;



use App\Models\PickupPoint;

use App\Models\PickupPointStaff;

use Closure;

use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;



class PvzMiddleware

{

    /** Только выдача уже прибывших заказов для заблокированного бывшего оператора. */

    protected const BLOCKED_ALLOWED_ROUTES = [

        'pvz.queue',

        'pvz.orders',

        'pvz.orders.status',

    ];



    public function handle(Request $request, Closure $next): Response

    {

        $user = $request->user();



        if (! $user) {

            abort(403);

        }



        $staff = $user->approvedPickupPointStaff()->with('pickupPoint')->first();

        $blockedOperator = (bool) $user->is_blocked && $staff;



        if (! $staff || $staff->status !== PickupPointStaff::STATUS_APPROVED || ! $staff->pickup_point_id) {

            if ($user->role === 'pvz') {

                return redirect()->route('pickup.partner')

                    ->with('error', 'Пункт выдачи не привязан или заявка не одобрена.');

            }



            abort(403);

        }



        if ($user->role !== 'pvz' && ! $blockedOperator) {

            abort(403);

        }



        if ($blockedOperator) {

            $routeName = $request->route()?->getName();

            if (! in_array($routeName, self::BLOCKED_ALLOWED_ROUTES, true)) {

                return redirect()->route('pvz.queue')

                    ->with('info', 'Аккаунт заблокирован. Доступна только выдача заказов, уже прибывших в пункт.');

            }

        }



        $point = $staff->pickupPoint;

        if (! $point || ($point->closure_status ?? PickupPoint::CLOSURE_NONE) === PickupPoint::CLOSURE_CLOSED) {

            return redirect()->route('pickup.partner')

                ->with('info', 'Пункт выдачи окончательно закрыт администратором. Панель недоступна.');

        }



        $request->attributes->set('pvz_staff', $staff);

        $request->attributes->set('pvz_pickup_point_id', (int) $staff->pickup_point_id);



        return $next($request);

    }

}


