<?php

namespace App\Console\Commands\Finance;

use App\Helpers\SysHelper;
use App\Models\Tenant\Finance\DayClosure;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Finance\TransactionPayment;
use App\Models\Tenant\Team;
use App\Models\Tenant\User;
use Illuminate\Console\Command;

class AutoDayClosure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:auto-day-closure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close the day in the finance module';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $teams = Team::query()
            ->get();

        foreach ($teams as $team) {
            SysHelper::setTeam($team->id);

            $uniqueUserIds = Transaction::query()
                ->leftJoin('periods', 'transactions.period_id', '=', 'periods.id')
                ->where('date', now()->toDateString())
                ->where('periods.team_id', $team->id)
                ->succeeded()
                ->distinct('user_id')
                ->pluck('user_id')
                ->all();

            $userIds = User::query()
                ->select('id')
                ->whereHas('roles', function ($q) {
                    $q->whereNotIn('name', ['student', 'guardian']);
                })
                ->whereIn('id', $uniqueUserIds)
                ->get()
                ->pluck('id')
                ->all();

            foreach ($userIds as $userId) {
                $userCollectedAmount = TransactionPayment::query()
                    ->whereHas('transaction', function ($q) use ($userId) {
                        $q->where('date', now()->toDateString())
                            ->where('user_id', $userId)
                            ->succeeded();
                    })
                    ->whereHas('method', function ($q) {
                        $q->where('name', 'Cash');
                    })
                    ->get()
                    ->sum('amount.value');

                $dayClosure = DayClosure::query()
                    ->whereTeamId($team->id)
                    ->whereUserId($userId)
                    ->where('date', now()->toDateString())
                    ->first();

                if (! $dayClosure) {
                    DayClosure::forceCreate([
                        'team_id' => $team->id,
                        'user_id' => $userId,
                        'date' => now()->toDateString(),
                        'remarks' => 'Auto closed by system',
                        'denominations' => [],
                        'total' => $userCollectedAmount,
                        'meta' => [
                            'type' => 'auto',
                        ],
                    ]);
                }
            }
        }
    }
}
