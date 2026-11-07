<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Models\Notice;
use App\Models\Promotion;
use App\Models\School;
use App\Models\User;
use App\Services\Dashboard\SchoolTrends;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Exercise the queries that have broken on Postgres after a MySQL-era fork.
 *
 * Runs at container start so a bad SQL shape fails the deploy instead of the
 * first staff click on the dashboard.
 */
class SmokeQueries extends Command
{
    /**
     * @var string
     */
    protected $signature = 'skuul:smoke-queries';

    /**
     * @var string
     */
    protected $description = 'Confirm the dashboard and notice queries run on this database';

    public function handle(SchoolTrends $trends): int
    {
        if (!Installation::isInstalled()) {
            $this->info('Skip smoke queries: the application is not installed yet.');

            return self::SUCCESS;
        }

        $school = School::query()->orderBy('id')->first();

        if ($school === null) {
            $this->info('Skip smoke queries: no school exists yet.');

            return self::SUCCESS;
        }

        school_context()->set($school, remember: false);

        $today = school_today()->toImmutable();
        $reader = User::query()->orderBy('id')->first();
        $learnersCount = DB::connection()->getDriverName() === 'pgsql'
            ? 'json_array_length(students::json)'
            : 'JSON_LENGTH(students)';

        $checks = [
            'enrolled students' => fn (): mixed => User::query()->ofSchool()->students()->enrolledStudents()->count(),
            'active notices' => fn (): mixed => Notice::query()->inSchool()->active()->count(),
            'fees by month' => fn (): mixed => $trends->feesByMonth($today),
            'owed by lateness' => fn (): mixed => $trends->owedByLateness($today),
            'cash by month' => fn (): mixed => $trends->cashByMonth($today),
            'attendance by week' => fn (): mixed => $trends->attendanceByWeek($today),
            'incidents by week' => fn (): mixed => $reader === null
                ? null
                : $trends->incidentsByWeek($reader, $today),
            'enrolment by class' => function () use ($trends): mixed {
                $year = current_academic_year();

                return $year === null ? null : $trends->enrolmentByClass($year->id);
            },
            'promotion learner sort' => fn (): mixed => Promotion::query()
                ->orderByRaw($learnersCount.' desc')
                ->limit(1)
                ->get(['id']),
        ];

        foreach ($checks as $label => $check) {
            try {
                $check();
                $this->info("PASS: {$label}");
            } catch (Throwable $failure) {
                $this->error("FAIL: {$label}");
                $this->error($failure->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Smoke queries passed on '.DB::connection()->getDriverName().'.');

        return self::SUCCESS;
    }
}
