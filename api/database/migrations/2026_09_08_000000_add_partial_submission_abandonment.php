<?php

use App\Models\Forms\FormSubmission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedSmallInteger('partial_submission_abandonment_value')->nullable();
            $table->string('partial_submission_abandonment_unit', 10)->nullable();
        });

        $this->updateSubmissionStatusConstraint([
            FormSubmission::STATUS_PARTIAL,
            FormSubmission::STATUS_COMPLETED,
            FormSubmission::STATUS_ABANDONED,
        ]);

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->index(
                ['form_id', 'status', 'updated_at'],
                'form_submissions_form_status_updated_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex('form_submissions_form_status_updated_at_index');
        });

        $this->updateSubmissionStatusConstraint([
            FormSubmission::STATUS_PARTIAL,
            FormSubmission::STATUS_COMPLETED,
        ]);

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn([
                'partial_submission_abandonment_value',
                'partial_submission_abandonment_unit',
            ]);
        });
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function updateSubmissionStatusConstraint(array $statuses): void
    {
        $driver = DB::getDriverName();
        $quoted = implode(', ', array_map(
            fn (string $status) => "'" . str_replace("'", "''", $status) . "'",
            $statuses
        ));

        if ($driver === 'mysql') {
            Schema::table('form_submissions', function (Blueprint $table) use ($statuses) {
                $table->enum('status', $statuses)
                    ->default(FormSubmission::STATUS_COMPLETED)
                    ->change();
            });

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE form_submissions DROP CONSTRAINT IF EXISTS form_submissions_status_check');
            DB::statement("ALTER TABLE form_submissions ADD CONSTRAINT form_submissions_status_check CHECK (status::text = ANY (ARRAY[{$quoted}]))");
        }
    }
};
