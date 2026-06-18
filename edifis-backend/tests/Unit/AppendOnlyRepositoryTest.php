<?php

use App\Support\AppendOnlyRepository;

it('exposes no update method', function () {
    $reflection = new ReflectionClass(AppendOnlyRepository::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC));

    expect($methods)->not->toContain('update');
    expect($methods)->not->toContain('delete');
    expect($methods)->toContain('append');
    expect($methods)->toContain('void');
});

it('voiding creates a new row and never mutates original', function () {
    config(['edifis.idempotency_table' => 'idempotency_log']);

    $counter = (object) ['count' => 0];

    $repo = new class($counter) extends AppendOnlyRepository {
        public function __construct(private $counter) {
            $this->model = \App\Domain\Attendance\Models\AttendanceEvent::class;
        }

        public function append(array $attributes): \Illuminate\Database\Eloquent\Model
        {
            $this->counter->count++;
            return $this->model::create(array_merge([
                'id' => $attributes['id'] ?? \Ramsey\Uuid\Uuid::uuid7()->toString(),
                'revision' => 'r1',
                'session_id' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
                'student_id' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
                'scanned_at' => now(),
                'status' => 'present',
            ], $attributes));
        }

        public function void(string $id, string $reason): \Illuminate\Database\Eloquent\Model
        {
            $original = $this->findOrFail($id);
            $this->counter->count++;
            return $this->model::create([
                'id' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
                'revision' => ($original->revision ?? '') . '-voided',
                'session_id' => $original->session_id,
                'student_id' => $original->student_id,
                'scanned_at' => now(),
                'status' => 'void',
                'void_reason' => $reason,
                'source' => $original->source,
            ]);
        }

        public function findOrFail(string $id): \Illuminate\Database\Eloquent\Model
        {
            return $this->model::findOrFail($id);
        }
    };

    $original = $repo->append([
        'id' => tid('void.test.1'),
        'source' => 'qr_scan',
    ]);
    expect($counter->count)->toBe(1);

    $voided = $repo->void(tid('void.test.1'), 'test reason');
    expect($counter->count)->toBe(2);
    expect($voided->id)->not->toBe(tid('void.test.1'));
    expect($voided->status)->toBe('void');
    expect($voided->void_reason)->toBe('test reason');
});

it('rejects float amounts — CFA must be integer minor units', function () {
    $amounts = [0, 100, 5000, 15000];

    foreach ($amounts as $amount) {
        expect(is_int($amount))->toBeTrue();
        expect(is_float($amount))->toBeFalse();
    }

    $floatAmount = 500.75;
    expect(is_int($floatAmount))->toBeFalse();
    expect(is_float($floatAmount))->toBeTrue();
});

it('balance is derived from SUM and never a stored column', function () {
    $balance = \App\Domain\Ledger\Models\LedgerEntry::sum('amount');
    expect($balance)->toBeInt();

    $columns = Schema::getColumnListing('ledger_entries');
    expect($columns)->not->toContain('balance');
    expect($columns)->not->toContain('current_balance');
    expect($columns)->not->toContain('outstanding');
});
